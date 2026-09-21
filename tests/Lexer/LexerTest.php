<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Tests\Lexer;

use Jthayne\FormulaEngine\Exception\SyntaxException;
use Jthayne\FormulaEngine\Lexer\Lexer;
use Jthayne\FormulaEngine\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    public function testTokenizesIfFormula(): void
    {
        $tokens = (new Lexer())->tokenize('If ([[Income]] < 1000)|"poor"|"rich"');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Identifier,
            TokenType::LParen,
            TokenType::Variable,
            TokenType::Operator,
            TokenType::Number,
            TokenType::RParen,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Eof,
        ], $types);

        self::assertSame('Income', $tokens[2]->value);
        self::assertSame('<', $tokens[3]->value);
        self::assertSame('1000', $tokens[4]->value);
        self::assertSame('poor', $tokens[7]->value);
        self::assertSame('rich', $tokens[9]->value);
    }

    public function testTokenizesCaseFormula(): void
    {
        $tokens = (new Lexer())->tokenize('Case ([[Status]])|"Approved","green"|"Denied","red"');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Identifier,
            TokenType::LParen,
            TokenType::Variable,
            TokenType::RParen,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Comma,
            TokenType::String,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Comma,
            TokenType::String,
            TokenType::Eof,
        ], $types);
    }

    public function testTrimsWhitespaceInsideVariableBrackets(): void
    {
        $tokens = (new Lexer())->tokenize('[[  Income  ]]');

        self::assertSame('Income', $tokens[0]->value);
    }

    public function testTokenizesNegativeAndDecimalNumbers(): void
    {
        $tokens = (new Lexer())->tokenize('-12.5');

        self::assertSame(TokenType::Number, $tokens[0]->type);
        self::assertSame('-12.5', $tokens[0]->value);
    }

    public function testTokenizesEscapedStringLiteral(): void
    {
        $tokens = (new Lexer())->tokenize('"say \\"hi\\" \\\\ ok"');

        self::assertSame(TokenType::String, $tokens[0]->type);
        self::assertSame('say "hi" \\ ok', $tokens[0]->value);
    }

    public function testTokenizesTwoCharacterOperators(): void
    {
        foreach (['<=', '>=', '==', '!=', '<>'] as $operator) {
            $tokens = (new Lexer())->tokenize("1 {$operator} 2");
            self::assertSame($operator, $tokens[1]->value, "Failed for operator {$operator}");
        }
    }

    public function testThrowsOnUnterminatedVariable(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated variable');

        (new Lexer())->tokenize('[[Income');
    }

    public function testThrowsOnUnterminatedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated string literal');

        (new Lexer())->tokenize('"poor');
    }

    public function testThrowsOnEmptyVariableName(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Variable name cannot be empty');

        (new Lexer())->tokenize('[[ ]]');
    }

    public function testThrowsOnUnexpectedCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected character "$"');

        (new Lexer())->tokenize('$foo');
    }
}
