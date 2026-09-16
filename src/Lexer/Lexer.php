<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Lexer;

use Jthayne\FormulaEngine\Exception\SyntaxException;

/**
 * Turns a raw formula string into a flat list of tokens.
 */
final class Lexer
{
    private const TWO_CHAR_OPERATORS = ['<=', '>=', '==', '!=', '<>'];
    private const ONE_CHAR_OPERATORS = ['<', '>', '='];

    /**
     * @return Token[]
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $position = 0;

        while ($position < $length) {
            $char = $source[$position];

            if (ctype_space($char)) {
                $position++;
                continue;
            }

            $simple = match ($char) {
                '(' => TokenType::LParen,
                ')' => TokenType::RParen,
                '|' => TokenType::Pipe,
                ',' => TokenType::Comma,
                default => null,
            };

            if ($simple !== null) {
                $tokens[] = new Token($simple, $char, $position);
                $position++;
                continue;
            }

            if ($char === '{') {
                [$token, $position] = $this->readVariable($source, $position);
                $tokens[] = $token;
                continue;
            }

            if ($char === '"') {
                [$token, $position] = $this->readString($source, $position);
                $tokens[] = $token;
                continue;
            }

            $two = substr($source, $position, 2);
            if (in_array($two, self::TWO_CHAR_OPERATORS, true)) {
                $tokens[] = new Token(TokenType::Operator, $two, $position);
                $position += 2;
                continue;
            }

            if (in_array($char, self::ONE_CHAR_OPERATORS, true)) {
                $tokens[] = new Token(TokenType::Operator, $char, $position);
                $position++;
                continue;
            }

            if (ctype_digit($char) || ($char === '-' && $this->isDigit($source, $position + 1))) {
                [$token, $position] = $this->readNumber($source, $position);
                $tokens[] = $token;
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                [$token, $position] = $this->readIdentifier($source, $position);
                $tokens[] = $token;
                continue;
            }

            throw new SyntaxException(sprintf('Unexpected character "%s"', $char), $position);
        }

        $tokens[] = new Token(TokenType::Eof, '', $length);

        return $tokens;
    }

    /**
     * @return array{0: Token, 1: int}
     */
    private function readVariable(string $source, int $start): array
    {
        $end = strpos($source, '}', $start + 1);

        if ($end === false) {
            throw new SyntaxException('Unterminated variable, missing "}"', $start);
        }

        $name = trim(substr($source, $start + 1, $end - $start - 1));

        if ($name === '') {
            throw new SyntaxException('Variable name cannot be empty', $start);
        }

        return [new Token(TokenType::Variable, $name, $start), $end + 1];
    }

    /**
     * @return array{0: Token, 1: int}
     */
    private function readString(string $source, int $start): array
    {
        $length = strlen($source);
        $buffer = '';
        $i = $start + 1;

        while ($i < $length && $source[$i] !== '"') {
            if ($source[$i] === '\\' && $i + 1 < $length) {
                $buffer .= $source[$i + 1];
                $i += 2;
                continue;
            }

            $buffer .= $source[$i];
            $i++;
        }

        if ($i >= $length) {
            throw new SyntaxException('Unterminated string literal, missing closing "', $start);
        }

        return [new Token(TokenType::String, $buffer, $start), $i + 1];
    }

    /**
     * @return array{0: Token, 1: int}
     */
    private function readNumber(string $source, int $start): array
    {
        $length = strlen($source);
        $position = $start + 1;

        while ($position < $length && ctype_digit($source[$position])) {
            $position++;
        }

        if ($position < $length && $source[$position] === '.' && $this->isDigit($source, $position + 1)) {
            $position++;
            while ($position < $length && ctype_digit($source[$position])) {
                $position++;
            }
        }

        return [new Token(TokenType::Number, substr($source, $start, $position - $start), $start), $position];
    }

    /**
     * @return array{0: Token, 1: int}
     */
    private function readIdentifier(string $source, int $start): array
    {
        $length = strlen($source);
        $position = $start + 1;

        while ($position < $length && (ctype_alnum($source[$position]) || $source[$position] === '_')) {
            $position++;
        }

        return [new Token(TokenType::Identifier, substr($source, $start, $position - $start), $start), $position];
    }

    private function isDigit(string $source, int $position): bool
    {
        return $position < strlen($source) && ctype_digit($source[$position]);
    }
}
