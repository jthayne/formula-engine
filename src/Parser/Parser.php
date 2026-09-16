<?php

declare(strict_types=1);

namespace Dloch\FormulaEngine\Parser;

use Dloch\FormulaEngine\Ast\BinaryExpressionNode;
use Dloch\FormulaEngine\Ast\CaseNode;
use Dloch\FormulaEngine\Ast\CaseWhenClause;
use Dloch\FormulaEngine\Ast\IfNode;
use Dloch\FormulaEngine\Ast\LiteralNode;
use Dloch\FormulaEngine\Ast\Node;
use Dloch\FormulaEngine\Ast\UnaryExpressionNode;
use Dloch\FormulaEngine\Ast\VariableNode;
use Dloch\FormulaEngine\Exception\SyntaxException;
use Dloch\FormulaEngine\Lexer\Token;
use Dloch\FormulaEngine\Lexer\TokenType;

/**
 * Recursive-descent parser that turns a token stream into a formula AST.
 *
 * Grammar (informal):
 *   formula    := ifExpr | caseExpr
 *   ifExpr     := "If" "(" expression ")" "|" expression "|" expression
 *   caseExpr   := "Case" "(" expression ")" ( "|" expression ( "," expression )? )+
 *   expression := logicalOr
 *   logicalOr  := logicalAnd ( "OR" logicalAnd )*
 *   logicalAnd := comparison ( "AND" comparison )*
 *   comparison := unary ( operator unary )?
 *   unary      := "NOT" unary | primary
 *   primary    := VARIABLE | STRING | NUMBER | "TRUE" | "FALSE" | "NULL"
 *                 | "(" expression ")" | ifExpr | caseExpr
 *
 * Note: because caseExpr consumes "|" branches greedily, a Case nested as
 * a non-final value inside another If/Case (e.g. an If's "then") must be
 * wrapped in parentheses so its branch loop stops at the enclosing ")"
 * instead of swallowing the outer formula's remaining "|" branches.
 */
final class Parser
{
    private int $index = 0;

    /**
     * @param Token[] $tokens
     */
    public function __construct(private readonly array $tokens)
    {
    }

    public function parse(): Node
    {
        $node = $this->parseFormula();
        $this->expect(TokenType::Eof, 'Unexpected trailing input after formula');

        return $node;
    }

    private function parseFormula(): Node
    {
        if ($this->checkKeyword('IF')) {
            return $this->parseIf();
        }

        if ($this->checkKeyword('CASE')) {
            return $this->parseCase();
        }

        throw new SyntaxException(
            sprintf('Expected "If" or "Case", found "%s"', $this->current()->value),
            $this->current()->position
        );
    }

    private function parseIf(): Node
    {
        $this->advance();
        $this->expect(TokenType::LParen, 'Expected "(" after "If"');
        $condition = $this->parseExpression();
        $this->expect(TokenType::RParen, 'Expected ")" after If condition');
        $this->expect(TokenType::Pipe, 'Expected "|" after If condition');
        $then = $this->parseExpression();
        $this->expect(TokenType::Pipe, 'Expected "|" separating If then/else branches');
        $else = $this->parseExpression();

        return new IfNode($condition, $then, $else);
    }

    private function parseCase(): Node
    {
        $this->advance();
        $this->expect(TokenType::LParen, 'Expected "(" after "Case"');
        $subject = $this->parseExpression();
        $this->expect(TokenType::RParen, 'Expected ")" after Case subject');

        $whenClauses = [];
        $default = null;

        while ($this->check(TokenType::Pipe)) {
            if ($default !== null) {
                throw new SyntaxException(
                    'A Case default branch must be the last branch',
                    $this->current()->position
                );
            }

            $this->advance();
            $value = $this->parseExpression();

            if ($this->check(TokenType::Comma)) {
                $this->advance();
                $result = $this->parseExpression();
                $whenClauses[] = new CaseWhenClause($value, $result);
            } else {
                $default = $value;
            }
        }

        if ($whenClauses === []) {
            throw new SyntaxException(
                'Case expression requires at least one "value,result" branch',
                $this->current()->position
            );
        }

        return new CaseNode($subject, $whenClauses, $default);
    }

    private function parseExpression(): Node
    {
        return $this->parseLogicalOr();
    }

    private function parseLogicalOr(): Node
    {
        $left = $this->parseLogicalAnd();

        while ($this->checkKeyword('OR')) {
            $this->advance();
            $left = new BinaryExpressionNode($left, 'OR', $this->parseLogicalAnd());
        }

        return $left;
    }

    private function parseLogicalAnd(): Node
    {
        $left = $this->parseComparison();

        while ($this->checkKeyword('AND')) {
            $this->advance();
            $left = new BinaryExpressionNode($left, 'AND', $this->parseComparison());
        }

        return $left;
    }

    private function parseComparison(): Node
    {
        $left = $this->parseUnary();

        if ($this->check(TokenType::Operator)) {
            $operator = $this->advance()->value;
            $right = $this->parseUnary();

            return new BinaryExpressionNode($left, $operator, $right);
        }

        return $left;
    }

    private function parseUnary(): Node
    {
        if ($this->checkKeyword('NOT')) {
            $this->advance();

            return new UnaryExpressionNode('NOT', $this->parseUnary());
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): Node
    {
        $token = $this->current();

        switch ($token->type) {
            case TokenType::Variable:
                $this->advance();

                return new VariableNode($token->value);

            case TokenType::String:
                $this->advance();

                return new LiteralNode($token->value);

            case TokenType::Number:
                $this->advance();

                return new LiteralNode(
                    str_contains($token->value, '.') ? (float) $token->value : (int) $token->value
                );

            case TokenType::LParen:
                $this->advance();
                $expression = $this->parseExpression();
                $this->expect(TokenType::RParen, 'Expected closing ")"');

                return $expression;

            case TokenType::Identifier:
                return match (strtoupper($token->value)) {
                    'TRUE' => $this->consumeLiteral(true),
                    'FALSE' => $this->consumeLiteral(false),
                    'NULL' => $this->consumeLiteral(null),
                    'IF' => $this->parseIf(),
                    'CASE' => $this->parseCase(),
                    default => throw new SyntaxException(
                        sprintf('Unexpected identifier "%s"', $token->value),
                        $token->position
                    ),
                };

            default:
                throw new SyntaxException(
                    sprintf('Unexpected token "%s"', $token->value),
                    $token->position
                );
        }
    }

    private function consumeLiteral(bool|null $value): Node
    {
        $this->advance();

        return new LiteralNode($value);
    }

    private function current(): Token
    {
        return $this->tokens[$this->index];
    }

    private function advance(): Token
    {
        return $this->tokens[$this->index++];
    }

    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function checkKeyword(string $keyword): bool
    {
        return $this->check(TokenType::Identifier) && strtoupper($this->current()->value) === $keyword;
    }

    private function expect(TokenType $type, string $message): Token
    {
        if (!$this->check($type)) {
            throw new SyntaxException(
                sprintf('%s, found "%s"', $message, $this->current()->value),
                $this->current()->position
            );
        }

        return $this->advance();
    }
}
