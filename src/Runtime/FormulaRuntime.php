<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Runtime;

use Jthayne\FormulaEngine\Exception\UndefinedFunctionException;
use Jthayne\FormulaEngine\Exception\UndefinedVariableException;

/**
 * Helper functions referenced by name from compiled PHP code, kept outside
 * of the compiled closures so the generated source stays small and
 * behaves identically to the {@see \Jthayne\FormulaEngine\Compiler\Evaluator}.
 */
final class FormulaRuntime
{
    public static function getVariable(array $variables, string $name): mixed
    {
        if (!array_key_exists($name, $variables)) {
            throw new UndefinedVariableException($name);
        }

        return $variables[$name];
    }

    /**
     * Call a named formula function with already-evaluated arguments.
     *
     * A caller-supplied function (e.g. one closing over an external
     * lookup, such as `GetNameFromID`) takes precedence over a built-in of
     * the same name.
     *
     * @param array<string, callable> $functions
     * @param list<mixed> $arguments
     */
    public static function callFunction(array $functions, string $name, array $arguments): mixed
    {
        $callable = $functions[$name] ?? self::builtInFunctions()[$name] ?? null;

        if ($callable === null) {
            throw new UndefinedFunctionException($name);
        }

        return $callable(...$arguments);
    }

    /**
     * Functions available to every formula unless a caller overrides them
     * with a function of the same name. Add a new built-in by adding one
     * entry here.
     *
     * @return array<string, callable>
     */
    private static function builtInFunctions(): array
    {
        return [
            'Today' => static fn (): string => (new \DateTimeImmutable())->format('Y-m-d'),
        ];
    }
}
