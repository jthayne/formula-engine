<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Tests\Compiler;

use Jthayne\FormulaEngine\Exception\UndefinedVariableException;
use Jthayne\FormulaEngine\FormulaEngine;
use PHPUnit\Framework\TestCase;

final class PhpCodeCompilerTest extends TestCase
{
    public function testGeneratesFunctionSourceContainingReturnStatement(): void
    {
        $code = (new FormulaEngine())->toPhpCode('If ({Income} < 1000)|"poor"|"rich"');

        self::assertStringContainsString('static function (array $variables): mixed', $code);
        self::assertStringContainsString('return', $code);
        self::assertStringContainsString("'poor'", $code);
        self::assertStringContainsString("'rich'", $code);
    }

    public function testGeneratedClosureEvaluatesIfCorrectly(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ({Income} < 1000)|"poor"|"rich"');

        self::assertSame('poor', $closure(['Income' => 500]));
        self::assertSame('rich', $closure(['Income' => 5000]));
    }

    public function testGeneratedClosureEvaluatesCaseCorrectly(): void
    {
        $closure = (new FormulaEngine())->toClosure(
            'Case ({Status})|"Approved","green"|"Denied","red"|"gray"'
        );

        self::assertSame('green', $closure(['Status' => 'Approved']));
        self::assertSame('red', $closure(['Status' => 'Denied']));
        self::assertSame('gray', $closure(['Status' => 'Pending']));
    }

    public function testGeneratedClosureThrowsOnUndefinedVariable(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ({Income} < 1000)|"poor"|"rich"');

        $this->expectException(UndefinedVariableException::class);

        $closure([]);
    }

    public function testGeneratedClosureHandlesLogicalOperators(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ({A} > 0 AND {B} > 0)|"both"|"not both"');

        self::assertSame('both', $closure(['A' => 1, 'B' => 1]));
        self::assertSame('not both', $closure(['A' => 1, 'B' => -1]));
    }

    public function testGeneratedClosureHandlesNestedFormulas(): void
    {
        $closure = (new FormulaEngine())->toClosure(
            'If ({A} < 1)|If ({B} < 1)|"both small"|"a small"|"neither"'
        );

        self::assertSame('both small', $closure(['A' => 0, 'B' => 0]));
        self::assertSame('a small', $closure(['A' => 0, 'B' => 5]));
        self::assertSame('neither', $closure(['A' => 5, 'B' => 5]));
    }
}
