<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine;

use Jthayne\FormulaEngine\Ast\Node;
use Jthayne\FormulaEngine\Compiler\Evaluator;
use Jthayne\FormulaEngine\Compiler\PhpCodeCompiler;
use Jthayne\FormulaEngine\Compiler\SqlCompiler;
use Jthayne\FormulaEngine\Compiler\VariableCollector;
use Jthayne\FormulaEngine\Lexer\Lexer;
use Jthayne\FormulaEngine\Parser\Parser;

/**
 * Entry point for parsing and compiling user-authored formulas such as:
 *
 *   If ({Income} < 1000)|"poor"|"rich"
 *   Case ({Status})|"Approved","green"|"Denied","red"
 */
final class FormulaEngine
{
    public function parse(string $formula): Node
    {
        $tokens = (new Lexer())->tokenize($formula);

        return (new Parser($tokens))->parse();
    }

    /**
     * Compile the formula into a raw SQL expression (a CASE WHEN clause).
     *
     * @param string $identifierQuoteChar Character used to quote variable
     *                                     names as SQL identifiers, e.g. '`'
     *                                     for MySQL or '"' for ANSI SQL.
     */
    public function toSql(string $formula, string $identifierQuoteChar = ''): string
    {
        return (new SqlCompiler($identifierQuoteChar))->compile($this->parse($formula));
    }

    /**
     * Compile the formula into the source code of a PHP anonymous function
     * that accepts an array of variables and returns the formula's result.
     */
    public function toPhpCode(string $formula): string
    {
        $expression = (new PhpCodeCompiler())->compile($this->parse($formula));

        return "static function (array \$variables): mixed {\n    return {$expression};\n}";
    }

    /**
     * Compile the formula into an actual, callable PHP Closure.
     */
    public function toClosure(string $formula): \Closure
    {
        $code = $this->toPhpCode($formula);

        /** @var \Closure $closure */
        $closure = eval("return {$code};");

        return $closure;
    }

    /**
     * Parse and immediately evaluate the formula against a set of variables.
     *
     * @param array<string, mixed> $variables
     */
    public function evaluate(string $formula, array $variables): mixed
    {
        return (new Evaluator($variables))->evaluate($this->parse($formula));
    }

    /**
     * Return the distinct names of every {Variable} referenced by the
     * formula, in order of first appearance.
     *
     * @return string[]
     */
    public function getVariables(string $formula): array
    {
        return (new VariableCollector())->collect($this->parse($formula));
    }
}
