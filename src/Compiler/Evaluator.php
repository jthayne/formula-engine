<?php

declare(strict_types=1);

namespace Dloch\FormulaEngine\Compiler;

use Dloch\FormulaEngine\Ast\BinaryExpressionNode;
use Dloch\FormulaEngine\Ast\CaseNode;
use Dloch\FormulaEngine\Ast\IfNode;
use Dloch\FormulaEngine\Ast\LiteralNode;
use Dloch\FormulaEngine\Ast\Node;
use Dloch\FormulaEngine\Ast\NodeVisitor;
use Dloch\FormulaEngine\Ast\UnaryExpressionNode;
use Dloch\FormulaEngine\Ast\VariableNode;
use Dloch\FormulaEngine\Exception\UndefinedVariableException;

/**
 * Directly evaluates a formula AST against a set of variables, without
 * generating any intermediate code.
 */
final class Evaluator implements NodeVisitor
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(private readonly array $variables)
    {
    }

    public function evaluate(Node $node): mixed
    {
        return $node->accept($this);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        if (!array_key_exists($node->name, $this->variables)) {
            throw new UndefinedVariableException($node->name);
        }

        return $this->variables[$node->name];
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return $node->value;
    }

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed
    {
        $operator = strtoupper($node->operator);

        if ($operator === 'AND') {
            return $this->toBool($node->left->accept($this)) && $this->toBool($node->right->accept($this));
        }

        if ($operator === 'OR') {
            return $this->toBool($node->left->accept($this)) || $this->toBool($node->right->accept($this));
        }

        $left = $node->left->accept($this);
        $right = $node->right->accept($this);

        return match ($operator) {
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '==', '=' => $left == $right,
            '!=', '<>' => $left != $right,
            default => throw new \LogicException(sprintf('Unsupported operator "%s"', $node->operator)),
        };
    }

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed
    {
        return match (strtoupper($node->operator)) {
            'NOT' => !$this->toBool($node->operand->accept($this)),
            default => throw new \LogicException(sprintf('Unsupported unary operator "%s"', $node->operator)),
        };
    }

    public function visitIf(IfNode $node): mixed
    {
        return $this->toBool($node->condition->accept($this))
            ? $node->then->accept($this)
            : $node->else->accept($this);
    }

    public function visitCase(CaseNode $node): mixed
    {
        $subject = $node->subject->accept($this);

        foreach ($node->whenClauses as $clause) {
            if ($subject == $clause->when->accept($this)) {
                return $clause->then->accept($this);
            }
        }

        return $node->default?->accept($this);
    }

    private function toBool(mixed $value): bool
    {
        return (bool) $value;
    }
}
