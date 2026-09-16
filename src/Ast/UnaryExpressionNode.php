<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Ast;

/**
 * A unary expression, currently only NOT.
 */
final class UnaryExpressionNode implements Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $operand,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitUnaryExpression($this);
    }
}
