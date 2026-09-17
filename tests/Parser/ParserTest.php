<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Tests\Parser;

use Jthayne\FormulaEngine\Ast\BinaryExpressionNode;
use Jthayne\FormulaEngine\Ast\CaseNode;
use Jthayne\FormulaEngine\Ast\FunctionCallNode;
use Jthayne\FormulaEngine\Ast\IfNode;
use Jthayne\FormulaEngine\Ast\LiteralNode;
use Jthayne\FormulaEngine\Ast\Node;
use Jthayne\FormulaEngine\Ast\UnaryExpressionNode;
use Jthayne\FormulaEngine\Ast\VariableNode;
use Jthayne\FormulaEngine\Exception\SyntaxException;
use Jthayne\FormulaEngine\Lexer\Lexer;
use Jthayne\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private function parse(string $formula): Node
    {
        $tokens = (new Lexer())->tokenize($formula);

        return (new Parser($tokens))->parse();
    }

    public function testParsesIfIntoIfNode(): void
    {
        $node = $this->parse('If ({Income} < 1000)|"poor"|"rich"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition);
        self::assertInstanceOf(VariableNode::class, $node->condition->left);
        self::assertSame('Income', $node->condition->left->name);
        self::assertSame('<', $node->condition->operator);
        self::assertInstanceOf(LiteralNode::class, $node->condition->right);
        self::assertSame(1000, $node->condition->right->value);
        self::assertSame('poor', $node->then->value);
        self::assertSame('rich', $node->else->value);
    }

    public function testParsesCaseIntoCaseNodeWithoutDefault(): void
    {
        $node = $this->parse('Case ({Status})|"Approved","green"|"Denied","red"');

        self::assertInstanceOf(CaseNode::class, $node);
        self::assertInstanceOf(VariableNode::class, $node->subject);
        self::assertSame('Status', $node->subject->name);
        self::assertCount(2, $node->whenClauses);
        self::assertSame('Approved', $node->whenClauses[0]->when->value);
        self::assertSame('green', $node->whenClauses[0]->then->value);
        self::assertSame('Denied', $node->whenClauses[1]->when->value);
        self::assertSame('red', $node->whenClauses[1]->then->value);
        self::assertNull($node->default);
    }

    public function testParsesCaseWithTrailingDefaultBranch(): void
    {
        $node = $this->parse('Case ({Status})|"Approved","green"|"Denied","red"|"gray"');

        self::assertInstanceOf(CaseNode::class, $node);
        self::assertCount(2, $node->whenClauses);
        self::assertInstanceOf(LiteralNode::class, $node->default);
        self::assertSame('gray', $node->default->value);
    }

    public function testParsesLogicalAndOrAndNot(): void
    {
        $node = $this->parse('If (NOT {A} AND {B} OR {C})|"yes"|"no"');

        self::assertInstanceOf(IfNode::class, $node);
        /** @var BinaryExpressionNode $or */
        $or = $node->condition;
        self::assertInstanceOf(BinaryExpressionNode::class, $or);
        self::assertSame('OR', $or->operator);

        /** @var BinaryExpressionNode $and */
        $and = $or->left;
        self::assertInstanceOf(BinaryExpressionNode::class, $and);
        self::assertSame('AND', $and->operator);
        self::assertInstanceOf(UnaryExpressionNode::class, $and->left);
        self::assertSame('NOT', $and->left->operator);
    }

    public function testParsesParenthesizedExpression(): void
    {
        $node = $this->parse('If (({A} AND {B}) OR {C})|1|2');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition);
        self::assertSame('OR', $node->condition->operator);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition->left);
        self::assertSame('AND', $node->condition->left->operator);
    }

    public function testParsesBooleanAndNullLiterals(): void
    {
        $node = $this->parse('If ({Active} == TRUE)|NULL|FALSE');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertTrue($node->condition->right->value);
        self::assertNull($node->then->value);
        self::assertFalse($node->else->value);
    }

    public function testParsesNestedFormulaAsResultValue(): void
    {
        $node = $this->parse('If ({A} < 1)|If ({B} < 1)|"both small"|"a small"|"neither"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(IfNode::class, $node->then);
        self::assertSame('both small', $node->then->then->value);
        self::assertSame('a small', $node->then->else->value);
        self::assertSame('neither', $node->else->value);
    }

    public function testParsesFunctionCallWithNoArguments(): void
    {
        $node = $this->parse('If (TRUE)|Today()|"no"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(FunctionCallNode::class, $node->then);
        self::assertSame('Today', $node->then->name);
        self::assertSame([], $node->then->arguments);
    }

    public function testParsesFunctionCallWithVariableAndMultipleArguments(): void
    {
        $node = $this->parse('If (TRUE)|Lookup({ID}, "type")|"no"');

        self::assertInstanceOf(FunctionCallNode::class, $node->then);
        self::assertSame('Lookup', $node->then->name);
        self::assertCount(2, $node->then->arguments);
        self::assertInstanceOf(VariableNode::class, $node->then->arguments[0]);
        self::assertSame('ID', $node->then->arguments[0]->name);
        self::assertSame('type', $node->then->arguments[1]->value);
    }

    public function testThrowsWhenFunctionCallMissingClosingParen(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If (TRUE)|Today(|"no"');
    }

    public function testThrowsWhenFormulaDoesNotStartWithKnownFunction(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected "If" or "Case"');

        $this->parse('Wat ({A} < 1)|1|2');
    }

    public function testThrowsWhenIfMissingSecondPipe(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If ({A} < 1)|"yes"');
    }

    public function testThrowsWhenCaseHasNoBranches(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('requires at least one');

        $this->parse('Case ({Status})');
    }

    public function testThrowsWhenDefaultBranchIsNotLast(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('must be the last branch');

        $this->parse('Case ({Status})|"gray"|"Approved","green"');
    }

    public function testThrowsOnTrailingInput(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected trailing input');

        $this->parse('If ({A} < 1)|1|2 extra');
    }

    public function testThrowsOnUnexpectedToken(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If (|)|1|2');
    }
}
