<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Compiler;

use Jthayne\FormulaEngine\Ast\BinaryExpressionNode;
use Jthayne\FormulaEngine\Ast\CaseNode;
use Jthayne\FormulaEngine\Ast\FunctionCallNode;
use Jthayne\FormulaEngine\Ast\IfNode;
use Jthayne\FormulaEngine\Ast\LiteralNode;
use Jthayne\FormulaEngine\Ast\Node;
use Jthayne\FormulaEngine\Ast\NodeVisitor;
use Jthayne\FormulaEngine\Ast\UnaryExpressionNode;
use Jthayne\FormulaEngine\Ast\VariableNode;

/**
 * Walks a formula AST and collects the distinct names of every {Variable}
 * it references, in order of first appearance.
 */
final class VariableCollector implements NodeVisitor
{
    /**
     * @var array<string, true>
     */
    private array $names = [];

    /**
     * @return string[]
     */
    public function collect(Node $node): array
    {
        $node->accept($this);

        return array_keys($this->names);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        $this->names[$node->name] = true;

        return null;
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return null;
    }

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed
    {
        $node->left->accept($this);
        $node->right->accept($this);

        return null;
    }

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed
    {
        $node->operand->accept($this);

        return null;
    }

    public function visitFunctionCall(FunctionCallNode $node): mixed
    {
        foreach ($node->arguments as $argument) {
            $argument->accept($this);
        }

        return null;
    }

    public function visitIf(IfNode $node): mixed
    {
        $node->condition->accept($this);
        $node->then->accept($this);
        $node->else->accept($this);

        return null;
    }

    public function visitCase(CaseNode $node): mixed
    {
        $node->subject->accept($this);

        foreach ($node->whenClauses as $clause) {
            $clause->when->accept($this);
            $clause->then->accept($this);
        }

        $node->default?->accept($this);

        return null;
    }
}
