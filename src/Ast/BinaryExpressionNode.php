<?php

declare(strict_types=1);

namespace Dloch\FormulaEngine\Ast;

/**
 * A comparison (<, <=, >, >=, ==, !=) or logical (AND, OR) expression.
 */
final class BinaryExpressionNode implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly string $operator,
        public readonly Node $right,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitBinaryExpression($this);
    }
}
