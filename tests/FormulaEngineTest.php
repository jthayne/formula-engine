<?php

declare(strict_types=1);

namespace Dloch\FormulaEngine\Tests;

use Dloch\FormulaEngine\Exception\SyntaxException;
use Dloch\FormulaEngine\FormulaEngine;
use PHPUnit\Framework\TestCase;

final class FormulaEngineTest extends TestCase
{
    public function testEvaluateReturnsExpectedResultForIf(): void
    {
        $engine = new FormulaEngine();

        self::assertSame('poor', $engine->evaluate('If ({Income} < 1000)|"poor"|"rich"', ['Income' => 100]));
        self::assertSame('rich', $engine->evaluate('If ({Income} < 1000)|"poor"|"rich"', ['Income' => 100000]));
    }

    public function testEvaluateReturnsExpectedResultForCase(): void
    {
        $engine = new FormulaEngine();
        $formula = 'Case ({Status})|"Approved","green"|"Denied","red"';

        self::assertSame('green', $engine->evaluate($formula, ['Status' => 'Approved']));
        self::assertSame('red', $engine->evaluate($formula, ['Status' => 'Denied']));
    }

    public function testToSqlAndToClosureAgreeWithEvaluate(): void
    {
        $engine = new FormulaEngine();
        $formula = 'Case ({Status})|"Approved","green"|"Denied","red"|"gray"';

        $sql = $engine->toSql($formula, '`');
        self::assertSame(
            "CASE WHEN (`Status`) = ('Approved') THEN 'green' WHEN (`Status`) = ('Denied') THEN 'red' ELSE 'gray' END",
            $sql
        );

        $closure = $engine->toClosure($formula);

        foreach (['Approved', 'Denied', 'Pending'] as $status) {
            self::assertSame(
                $engine->evaluate($formula, ['Status' => $status]),
                $closure(['Status' => $status])
            );
        }
    }

    public function testInvalidFormulaThrowsSyntaxException(): void
    {
        $engine = new FormulaEngine();

        $this->expectException(SyntaxException::class);

        $engine->evaluate('NotAFunction ({A})|1|2', []);
    }
}
