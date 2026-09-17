<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Tests\Compiler;

use Jthayne\FormulaEngine\Compiler\Evaluator;
use Jthayne\FormulaEngine\Exception\UndefinedFunctionException;
use Jthayne\FormulaEngine\Exception\UndefinedVariableException;
use Jthayne\FormulaEngine\Lexer\Lexer;
use Jthayne\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
    private function evaluate(string $formula, array $variables, array $functions = []): mixed
    {
        $tokens = (new Lexer())->tokenize($formula);
        $ast = (new Parser($tokens))->parse();

        return (new Evaluator($variables, $functions))->evaluate($ast);
    }

    public function testEvaluatesIfTrueBranch(): void
    {
        $result = $this->evaluate('If ({Income} < 1000)|"poor"|"rich"', ['Income' => 500]);

        self::assertSame('poor', $result);
    }

    public function testEvaluatesIfFalseBranch(): void
    {
        $result = $this->evaluate('If ({Income} < 1000)|"poor"|"rich"', ['Income' => 5000]);

        self::assertSame('rich', $result);
    }

    public function testEvaluatesCaseMatchingBranch(): void
    {
        $formula = 'Case ({Status})|"Approved","green"|"Denied","red"';

        self::assertSame('green', $this->evaluate($formula, ['Status' => 'Approved']));
        self::assertSame('red', $this->evaluate($formula, ['Status' => 'Denied']));
    }

    public function testEvaluatesCaseDefaultBranch(): void
    {
        $formula = 'Case ({Status})|"Approved","green"|"Denied","red"|"gray"';

        self::assertSame('gray', $this->evaluate($formula, ['Status' => 'Pending']));
    }

    public function testEvaluatesCaseWithoutDefaultReturnsNull(): void
    {
        $formula = 'Case ({Status})|"Approved","green"|"Denied","red"';

        self::assertNull($this->evaluate($formula, ['Status' => 'Pending']));
    }

    public function testEvaluatesLogicalAndOr(): void
    {
        $formula = 'If ({A} > 0 AND {B} > 0)|"both positive"|"not both"';

        self::assertSame('both positive', $this->evaluate($formula, ['A' => 1, 'B' => 1]));
        self::assertSame('not both', $this->evaluate($formula, ['A' => 1, 'B' => -1]));
    }

    public function testEvaluatesNot(): void
    {
        $formula = 'If (NOT ({Active} == TRUE))|"inactive"|"active"';

        self::assertSame('inactive', $this->evaluate($formula, ['Active' => false]));
        self::assertSame('active', $this->evaluate($formula, ['Active' => true]));
    }

    public function testEvaluatesNestedFormulas(): void
    {
        $formula = 'If ({A} < 1)|If ({B} < 1)|"both small"|"a small"|"neither"';

        self::assertSame('both small', $this->evaluate($formula, ['A' => 0, 'B' => 0]));
        self::assertSame('a small', $this->evaluate($formula, ['A' => 0, 'B' => 5]));
        self::assertSame('neither', $this->evaluate($formula, ['A' => 5, 'B' => 5]));
    }

    public function testThrowsOnUndefinedVariable(): void
    {
        $this->expectException(UndefinedVariableException::class);
        $this->expectExceptionMessage('Undefined variable "Income"');

        $this->evaluate('If ({Income} < 1000)|"poor"|"rich"', []);
    }

    public function testTreatsNullVariableAsDefined(): void
    {
        $result = $this->evaluate('If ({Income} == NULL)|"unknown"|"known"', ['Income' => null]);

        self::assertSame('unknown', $result);
    }

    public function testEvaluatesBuiltInTodayFunctionWithNoArguments(): void
    {
        $result = $this->evaluate('If ({Date} == Today())|"today"|"not today"', [
            'Date' => (new \DateTimeImmutable())->format('Y-m-d'),
        ]);

        self::assertSame('today', $result);
    }

    public function testEvaluatesCallerSuppliedFunctionWithVariableArgument(): void
    {
        $formula = 'If ({ID} > 0)|GetNameFromID({ID})|"none"';

        $result = $this->evaluate($formula, ['ID' => 7], [
            'GetNameFromID' => fn (int $id): string => "User-{$id}",
        ]);

        self::assertSame('User-7', $result);
    }

    public function testCallerSuppliedFunctionOverridesBuiltIn(): void
    {
        $result = $this->evaluate('If (TRUE)|Today()|"no"', [], [
            'Today' => fn (): string => 'overridden',
        ]);

        self::assertSame('overridden', $result);
    }

    public function testThrowsOnUndefinedFunction(): void
    {
        $this->expectException(UndefinedFunctionException::class);
        $this->expectExceptionMessage('Undefined function "NotAFunction"');

        $this->evaluate('If (TRUE)|NotAFunction()|"no"', []);
    }
}
