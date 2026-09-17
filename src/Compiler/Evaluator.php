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
use Jthayne\FormulaEngine\Exception\UndefinedVariableException;
use Jthayne\FormulaEngine\Runtime\FormulaRuntime;

/**
 * Directly evaluates a formula AST against a set of variables, without
 * generating any intermediate code.
 */
final class Evaluator implements NodeVisitor
{
    /**
     * @param array<string, mixed> $variables
     * @param array<string, callable> $functions Caller-supplied functions
     *                                            (e.g. one closing over an
     *                                            external lookup), keyed by
     *                                            the name used in the
     *                                            formula. May override a
     *                                            built-in function.
     */
    public function __construct(
        private readonly array $variables,
        private readonly array $functions = [],
    ) {
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

    public function visitFunctionCall(FunctionCallNode $node): mixed
    {
        $arguments = array_map(
            fn (Node $argument): mixed => $argument->accept($this),
            $node->arguments
        );

        return FormulaRuntime::callFunction($this->functions, $node->name, $arguments);
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
