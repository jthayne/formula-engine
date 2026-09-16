<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Runtime;

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
}
