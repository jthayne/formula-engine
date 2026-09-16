<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Compiler;

use Jthayne\FormulaEngine\Ast\BinaryExpressionNode;
use Jthayne\FormulaEngine\Ast\CaseNode;
use Jthayne\FormulaEngine\Ast\IfNode;
use Jthayne\FormulaEngine\Ast\LiteralNode;
use Jthayne\FormulaEngine\Ast\Node;
use Jthayne\FormulaEngine\Ast\NodeVisitor;
use Jthayne\FormulaEngine\Ast\UnaryExpressionNode;
use Jthayne\FormulaEngine\Ast\VariableNode;

/**
 * Compiles a formula AST into a raw SQL expression using CASE WHEN.
 */
final class SqlCompiler implements NodeVisitor
{
    /**
     * @param string $identifierQuoteChar Character used to quote variable
     *                                     names as SQL identifiers, e.g. '`'
     *                                     for MySQL or '"' for ANSI SQL.
     *                                     Empty string disables quoting.
     */
    public function __construct(private readonly string $identifierQuoteChar = '')
    {
    }

    public function compile(Node $node): string
    {
        return $node->accept($this);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        return $this->quoteIdentifier($node->name);
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        $value = $node->value;

        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => "'" . str_replace("'", "''", $value) . "'",
        };
    }

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed
    {
        $operator = strtoupper($node->operator);

        $sqlOperator = match ($operator) {
            'AND' => 'AND',
            'OR' => 'OR',
            '==' => '=',
            '!=', '<>' => '<>',
            '=', '<', '<=', '>', '>=' => $node->operator,
            default => throw new \LogicException(sprintf('Unsupported operator "%s"', $node->operator)),
        };

        return sprintf('(%s %s %s)', $node->left->accept($this), $sqlOperator, $node->right->accept($this));
    }

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed
    {
        return match (strtoupper($node->operator)) {
            'NOT' => sprintf('NOT (%s)', $node->operand->accept($this)),
            default => throw new \LogicException(sprintf('Unsupported unary operator "%s"', $node->operator)),
        };
    }

    public function visitIf(IfNode $node): mixed
    {
        return sprintf(
            'CASE WHEN %s THEN %s ELSE %s END',
            $node->condition->accept($this),
            $node->then->accept($this),
            $node->else->accept($this)
        );
    }

    public function visitCase(CaseNode $node): mixed
    {
        $subjectSql = $node->subject->accept($this);
        $sql = 'CASE';

        foreach ($node->whenClauses as $clause) {
            $sql .= sprintf(
                ' WHEN (%s) = (%s) THEN %s',
                $subjectSql,
                $clause->when->accept($this),
                $clause->then->accept($this)
            );
        }

        if ($node->default !== null) {
            $sql .= ' ELSE ' . $node->default->accept($this);
        }

        return $sql . ' END';
    }

    private function quoteIdentifier(string $name): string
    {
        if ($this->identifierQuoteChar === '') {
            return $name;
        }

        $escaped = str_replace(
            $this->identifierQuoteChar,
            $this->identifierQuoteChar . $this->identifierQuoteChar,
            $name
        );

        return $this->identifierQuoteChar . $escaped . $this->identifierQuoteChar;
    }
}
