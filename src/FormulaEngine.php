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
 *   If ({SignupDate} == Today())|"new"|GetNameFromID({ID})
 *
 * Functions such as `Today()` or a caller-supplied `GetNameFromID({ID})` can
 * be called by name; see the `$functions` parameter of {@see evaluate()}
 * and {@see toClosure()}.
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
     * that accepts an array of variables and an array of functions, and
     * returns the formula's result.
     */
    public function toPhpCode(string $formula): string
    {
        $expression = (new PhpCodeCompiler())->compile($this->parse($formula));

        return "static function (array \$variables, array \$functions = []): mixed {\n    return {$expression};\n}";
    }

    /**
     * Compile the formula into an actual, callable PHP Closure.
     *
     * The returned closure accepts `(array $variables, array $functions = [])`;
     * see {@see evaluate()} for what `$functions` is for.
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
     * @param array<string, callable> $functions Functions callable by name
     *                                            from the formula (e.g.
     *                                            `Today()`, or a
     *                                            caller-supplied
     *                                            `GetNameFromID({ID})`
     *                                            closing over an external
     *                                            lookup). A function here
     *                                            overrides a built-in of
     *                                            the same name.
     */
    public function evaluate(string $formula, array $variables, array $functions = []): mixed
    {
        return (new Evaluator($variables, $functions))->evaluate($this->parse($formula));
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
