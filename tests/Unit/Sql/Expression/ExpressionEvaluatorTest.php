<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Expression;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Sql\Expression\ExpressionEvaluator;
use Touta\Ogam\Sql\Expression\ExpressionSecurityException;

/**
 * TDD Tests for Expression Evaluator.
 *
 * The expression evaluator supports a safe subset of PHP-like expressions:
 * - Property access: name, user.email
 * - Comparison: ==, ===, !=, !==, <, >, <=, >=
 * - Logical: &&, ||, !
 * - Null checks: !== null, === null
 * - Literals: null, true, false, 'string', "string", numbers
 * - Parentheses for grouping
 *
 * Security: No function calls, no assignments, no dangerous operations
 */
final class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator();
    }

    // ========================================================================
    // Simple Property Access
    // ========================================================================

    public function testEvaluateSimpleProperty(): void
    {
        $bindings = ['name' => 'John'];

        $this->assertSame('John', $this->evaluator->evaluate('name', $bindings));
    }

    public function testEvaluateNestedProperty(): void
    {
        $bindings = ['user' => ['email' => 'john@example.com']];

        $this->assertSame('john@example.com', $this->evaluator->evaluate('user.email', $bindings));
    }

    public function testEvaluateMissingPropertyReturnsNull(): void
    {
        $bindings = ['name' => 'John'];

        $this->assertNull($this->evaluator->evaluate('missing', $bindings));
    }

    public function testEvaluateDeepNestedProperty(): void
    {
        $bindings = [
            'user' => [
                'profile' => [
                    'settings' => [
                        'theme' => 'dark',
                    ],
                ],
            ],
        ];

        $this->assertSame('dark', $this->evaluator->evaluate('user.profile.settings.theme', $bindings));
    }

    // ========================================================================
    // Comparison Operators
    // ========================================================================

    public function testEqualsOperator(): void
    {
        $bindings = ['status' => 'active'];

        $this->assertTrue($this->evaluator->evaluate("status == 'active'", $bindings));
        $this->assertFalse($this->evaluator->evaluate("status == 'inactive'", $bindings));
    }

    public function testNotEqualsOperator(): void
    {
        $bindings = ['status' => 'active'];

        $this->assertTrue($this->evaluator->evaluate("status != 'inactive'", $bindings));
        $this->assertFalse($this->evaluator->evaluate("status != 'active'", $bindings));
    }

    public function testStrictEqualsOperator(): void
    {
        $bindings = ['count' => 5];

        $this->assertTrue($this->evaluator->evaluate('count === 5', $bindings));
        $this->assertFalse($this->evaluator->evaluate("count === '5'", $bindings));
    }

    public function testStrictNotEqualsOperator(): void
    {
        $bindings = ['count' => 5];

        $this->assertTrue($this->evaluator->evaluate("count !== '5'", $bindings));
        $this->assertFalse($this->evaluator->evaluate('count !== 5', $bindings));
    }

    public function testLessThanOperator(): void
    {
        $bindings = ['age' => 25];

        $this->assertTrue($this->evaluator->evaluate('age < 30', $bindings));
        $this->assertFalse($this->evaluator->evaluate('age < 20', $bindings));
    }

    public function testGreaterThanOperator(): void
    {
        $bindings = ['age' => 25];

        $this->assertTrue($this->evaluator->evaluate('age > 20', $bindings));
        $this->assertFalse($this->evaluator->evaluate('age > 30', $bindings));
    }

    public function testLessThanOrEqualOperator(): void
    {
        $bindings = ['age' => 25];

        $this->assertTrue($this->evaluator->evaluate('age <= 25', $bindings));
        $this->assertTrue($this->evaluator->evaluate('age <= 30', $bindings));
        $this->assertFalse($this->evaluator->evaluate('age <= 20', $bindings));
    }

    public function testGreaterThanOrEqualOperator(): void
    {
        $bindings = ['age' => 25];

        $this->assertTrue($this->evaluator->evaluate('age >= 25', $bindings));
        $this->assertTrue($this->evaluator->evaluate('age >= 20', $bindings));
        $this->assertFalse($this->evaluator->evaluate('age >= 30', $bindings));
    }

    // ========================================================================
    // Null Checks
    // ========================================================================

    public function testNullComparison(): void
    {
        $bindings = ['name' => 'John', 'missing' => null];

        $this->assertTrue($this->evaluator->evaluate('name !== null', $bindings));
        $this->assertFalse($this->evaluator->evaluate('name === null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('missing === null', $bindings));
        $this->assertFalse($this->evaluator->evaluate('missing !== null', $bindings));
    }

    public function testMissingPropertyNullComparison(): void
    {
        $bindings = ['name' => 'John'];

        // Missing properties are treated as null
        $this->assertTrue($this->evaluator->evaluate('missing === null', $bindings));
        $this->assertFalse($this->evaluator->evaluate('missing !== null', $bindings));
    }

    public function testNullNotEqualsValue(): void
    {
        $bindings = ['status' => null];

        $this->assertTrue($this->evaluator->evaluate("status != 'active'", $bindings));
    }

    // ========================================================================
    // Logical Operators
    // ========================================================================

    public function testLogicalAnd(): void
    {
        $bindings = ['a' => true, 'b' => true, 'c' => false];

        $this->assertTrue($this->evaluator->evaluate('a && b', $bindings));
        $this->assertFalse($this->evaluator->evaluate('a && c', $bindings));
        $this->assertFalse($this->evaluator->evaluate('c && b', $bindings));
    }

    public function testLogicalOr(): void
    {
        $bindings = ['a' => true, 'b' => false, 'c' => false];

        $this->assertTrue($this->evaluator->evaluate('a || b', $bindings));
        $this->assertTrue($this->evaluator->evaluate('b || a', $bindings));
        $this->assertFalse($this->evaluator->evaluate('b || c', $bindings));
    }

    public function testLogicalNot(): void
    {
        $bindings = ['enabled' => true, 'disabled' => false];

        $this->assertFalse($this->evaluator->evaluate('!enabled', $bindings));
        $this->assertTrue($this->evaluator->evaluate('!disabled', $bindings));
    }

    public function testLogicalNotWithComparison(): void
    {
        $bindings = ['status' => 'active'];

        $this->assertTrue($this->evaluator->evaluate("!(status == 'inactive')", $bindings));
    }

    public function testComplexLogicalExpression(): void
    {
        $bindings = ['age' => 25, 'status' => 'active'];

        $this->assertTrue($this->evaluator->evaluate("age >= 18 && status == 'active'", $bindings));
        $this->assertFalse($this->evaluator->evaluate("age >= 30 && status == 'active'", $bindings));
        $this->assertTrue($this->evaluator->evaluate("age >= 30 || status == 'active'", $bindings));
    }

    // ========================================================================
    // Parentheses
    // ========================================================================

    public function testParenthesesGrouping(): void
    {
        $bindings = ['a' => true, 'b' => false, 'c' => true];

        // Without parentheses: a && b || c = false || true = true
        $this->assertTrue($this->evaluator->evaluate('a && b || c', $bindings));

        // With parentheses: a && (b || c) = true && true = true
        $this->assertTrue($this->evaluator->evaluate('a && (b || c)', $bindings));

        // Different grouping: (a && b) || c = false || true = true
        $this->assertTrue($this->evaluator->evaluate('(a && b) || c', $bindings));
    }

    public function testNestedParentheses(): void
    {
        $bindings = ['a' => true, 'b' => false, 'c' => true, 'd' => false];

        $this->assertTrue($this->evaluator->evaluate('((a && c) || (b && d))', $bindings));
        $this->assertFalse($this->evaluator->evaluate('((a && b) || (c && d))', $bindings));
    }

    // ========================================================================
    // Literals
    // ========================================================================

    public function testBooleanLiterals(): void
    {
        $bindings = [];

        $this->assertTrue($this->evaluator->evaluate('true', $bindings));
        $this->assertFalse($this->evaluator->evaluate('false', $bindings));
    }

    public function testNullLiteral(): void
    {
        $bindings = [];

        $this->assertNull($this->evaluator->evaluate('null', $bindings));
    }

    public function testIntegerLiteral(): void
    {
        $bindings = [];

        $this->assertSame(42, $this->evaluator->evaluate('42', $bindings));
        $this->assertSame(-10, $this->evaluator->evaluate('-10', $bindings));
    }

    public function testFloatLiteral(): void
    {
        $bindings = [];

        $this->assertSame(3.14, $this->evaluator->evaluate('3.14', $bindings));
        $this->assertSame(-2.5, $this->evaluator->evaluate('-2.5', $bindings));
    }

    public function testSingleQuotedStringLiteral(): void
    {
        $bindings = [];

        $this->assertSame('hello', $this->evaluator->evaluate("'hello'", $bindings));
        $this->assertSame('hello world', $this->evaluator->evaluate("'hello world'", $bindings));
    }

    public function testDoubleQuotedStringLiteral(): void
    {
        $bindings = [];

        $this->assertSame('hello', $this->evaluator->evaluate('"hello"', $bindings));
        $this->assertSame('hello world', $this->evaluator->evaluate('"hello world"', $bindings));
    }

    public function testEmptyStringLiteral(): void
    {
        $bindings = [];

        $this->assertSame('', $this->evaluator->evaluate("''", $bindings));
        $this->assertSame('', $this->evaluator->evaluate('""', $bindings));
    }

    // ========================================================================
    // Mixed Expressions
    // ========================================================================

    public function testPropertyComparedToLiteral(): void
    {
        $bindings = ['name' => 'John', 'age' => 25];

        $this->assertTrue($this->evaluator->evaluate("name == 'John'", $bindings));
        $this->assertTrue($this->evaluator->evaluate('age == 25', $bindings));
        $this->assertFalse($this->evaluator->evaluate('age == 30', $bindings));
    }

    public function testLiteralComparedToProperty(): void
    {
        $bindings = ['count' => 10];

        $this->assertTrue($this->evaluator->evaluate('5 < count', $bindings));
        $this->assertFalse($this->evaluator->evaluate('15 < count', $bindings));
    }

    public function testComplexRealWorldExpression(): void
    {
        $bindings = [
            'user' => [
                'status' => 'active',
                'age' => 25,
            ],
            'includeInactive' => false,
        ];

        $expression = "(user.status == 'active' || includeInactive) && user.age >= 18";
        $this->assertTrue($this->evaluator->evaluate($expression, $bindings));

        $bindings['user']['age'] = 15;
        $this->assertFalse($this->evaluator->evaluate($expression, $bindings));
    }

    // ========================================================================
    // Object Support
    // ========================================================================

    public function testObjectPropertyAccess(): void
    {
        $user = new class {
            public string $name = 'John';

            private int $age = 25;

            public function getAge(): int
            {
                return $this->age;
            }

            public function isActive(): bool
            {
                return true;
            }
        };

        $bindings = ['user' => $user];

        $this->assertSame('John', $this->evaluator->evaluate('user.name', $bindings));
        $this->assertSame(25, $this->evaluator->evaluate('user.age', $bindings));
        $this->assertTrue($this->evaluator->evaluate('user.active', $bindings));
    }

    public function testObjectComparisonExpression(): void
    {
        $user = new class {
            public string $status = 'active';
        };

        $bindings = ['user' => $user];

        $this->assertTrue($this->evaluator->evaluate("user.status == 'active'", $bindings));
        $this->assertTrue($this->evaluator->evaluate('user.status !== null', $bindings));
    }

    // ========================================================================
    // Whitespace Handling
    // ========================================================================

    public function testWhitespaceAroundOperators(): void
    {
        $bindings = ['a' => 5, 'b' => 10];

        $this->assertTrue($this->evaluator->evaluate('a==5', $bindings));
        $this->assertTrue($this->evaluator->evaluate('a == 5', $bindings));
        $this->assertTrue($this->evaluator->evaluate('a  ==  5', $bindings));
        $this->assertTrue($this->evaluator->evaluate('a<b', $bindings));
        $this->assertTrue($this->evaluator->evaluate('a < b', $bindings));
    }

    public function testLeadingAndTrailingWhitespace(): void
    {
        $bindings = ['name' => 'John'];

        $this->assertSame('John', $this->evaluator->evaluate('  name  ', $bindings));
        $this->assertTrue($this->evaluator->evaluate("  name == 'John'  ", $bindings));
    }

    // ========================================================================
    // Security: Dangerous Expressions Must Throw
    // ========================================================================

    public function testRejectsFunctionCalls(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Function calls are not allowed');

        $this->evaluator->evaluate('count($items)', $bindings);
    }

    public function testRejectsMethodCalls(): void
    {
        $user = new class {
            public function delete(): void {}
        };

        $bindings = ['user' => $user];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Method calls are not allowed');

        $this->evaluator->evaluate('user.delete()', $bindings);
    }

    public function testRejectsAssignments(): void
    {
        $bindings = ['name' => 'John'];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Assignments are not allowed');

        $this->evaluator->evaluate("name = 'hacked'", $bindings);
    }

    public function testRejectsCompoundAssignments(): void
    {
        $bindings = ['count' => 5];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Assignments are not allowed');

        $this->evaluator->evaluate('count += 10', $bindings);
    }

    public function testRejectsIncrementOperator(): void
    {
        $bindings = ['count' => 5];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Increment/decrement operators are not allowed');

        $this->evaluator->evaluate('count++', $bindings);
    }

    public function testRejectsDecrementOperator(): void
    {
        $bindings = ['count' => 5];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Increment/decrement operators are not allowed');

        $this->evaluator->evaluate('--count', $bindings);
    }

    public function testRejectsBacktickExecution(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Shell execution is not allowed');

        $this->evaluator->evaluate('`ls -la`', $bindings);
    }

    public function testRejectsExecFunction(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Function calls are not allowed');

        $this->evaluator->evaluate("exec('rm -rf /')", $bindings);
    }

    public function testRejectsEvalFunction(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Function calls are not allowed');

        $this->evaluator->evaluate("eval('malicious code')", $bindings);
    }

    public function testRejectsSystemFunction(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Function calls are not allowed');

        $this->evaluator->evaluate("system('whoami')", $bindings);
    }

    public function testRejectsIncludeConstruct(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Include/require constructs are not allowed');

        $this->evaluator->evaluate("include '/etc/passwd'", $bindings);
    }

    public function testRejectsRequireConstruct(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Include/require constructs are not allowed');

        $this->evaluator->evaluate("require 'evil.php'", $bindings);
    }

    public function testRejectsNewOperator(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Object instantiation is not allowed');

        $this->evaluator->evaluate('new stdClass()', $bindings);
    }

    public function testRejectsStaticMethodCalls(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Static access is not allowed');

        $this->evaluator->evaluate('SomeClass::dangerousMethod()', $bindings);
    }

    public function testRejectsArrayAccess(): void
    {
        $bindings = ['items' => [1, 2, 3]];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Array access syntax is not allowed');

        $this->evaluator->evaluate('items[0]', $bindings);
    }

    public function testRejectsGlobalVariables(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Global variables are not allowed');

        $this->evaluator->evaluate('$_GET', $bindings);
    }

    public function testRejectsVariableVariables(): void
    {
        $bindings = ['name' => 'secret'];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Variable variables are not allowed');

        $this->evaluator->evaluate('$$name', $bindings);
    }

    public function testRejectsArrowFunctions(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Anonymous functions are not allowed');

        $this->evaluator->evaluate('fn($x) => $x * 2', $bindings);
    }

    public function testRejectsClosures(): void
    {
        $bindings = [];

        $this->expectException(ExpressionSecurityException::class);
        $this->expectExceptionMessage('Anonymous functions are not allowed');

        $this->evaluator->evaluate('function() { return true; }', $bindings);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    public function testEmptyExpression(): void
    {
        $bindings = [];

        $this->assertNull($this->evaluator->evaluate('', $bindings));
    }

    public function testWhitespaceOnlyExpression(): void
    {
        $bindings = [];

        $this->assertNull($this->evaluator->evaluate('   ', $bindings));
    }

    public function testEscapedQuotesInString(): void
    {
        $bindings = [];

        $this->assertSame("it's", $this->evaluator->evaluate("'it\\'s'", $bindings));
        $this->assertSame('say "hello"', $this->evaluator->evaluate('"say \\"hello\\""', $bindings));
    }

    public function testSpecialCharactersInStringLiteral(): void
    {
        $bindings = [];

        $this->assertSame('hello\\nworld', $this->evaluator->evaluate("'hello\\\\nworld'", $bindings));
    }

    public function testComparisonWithPropertyOnBothSides(): void
    {
        $bindings = ['a' => 10, 'b' => 5];

        $this->assertTrue($this->evaluator->evaluate('a > b', $bindings));
        $this->assertTrue($this->evaluator->evaluate('b < a', $bindings));
        $this->assertFalse($this->evaluator->evaluate('a == b', $bindings));
    }

    public function testMultipleLogicalOperatorsSamePrecedence(): void
    {
        $bindings = ['a' => true, 'b' => true, 'c' => true];

        // Left to right evaluation for same precedence
        $this->assertTrue($this->evaluator->evaluate('a && b && c', $bindings));
        $this->assertTrue($this->evaluator->evaluate('a || b || c', $bindings));
    }

    public function testOperatorPrecedence(): void
    {
        $bindings = ['a' => true, 'b' => false, 'c' => true];

        // && has higher precedence than ||
        // a || b && c = a || (b && c) = true || false = true
        $this->assertTrue($this->evaluator->evaluate('a || b && c', $bindings));

        // b && c || a = (b && c) || a = false || true = true
        $this->assertTrue($this->evaluator->evaluate('b && c || a', $bindings));
    }

    public function testZeroIsNotNull(): void
    {
        $bindings = ['count' => 0];

        $this->assertFalse($this->evaluator->evaluate('count === null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('count !== null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('count == 0', $bindings));
    }

    public function testEmptyStringIsNotNull(): void
    {
        $bindings = ['name' => ''];

        $this->assertFalse($this->evaluator->evaluate('name === null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('name !== null', $bindings));
        $this->assertTrue($this->evaluator->evaluate("name == ''", $bindings));
    }

    public function testFalseIsNotNull(): void
    {
        $bindings = ['enabled' => false];

        $this->assertFalse($this->evaluator->evaluate('enabled === null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('enabled !== null', $bindings));
        $this->assertTrue($this->evaluator->evaluate('enabled == false', $bindings));
    }

    // ========================================================================
    // Boolean Evaluation (for if nodes)
    // ========================================================================

    public function testEvaluateBooleanTruthyValues(): void
    {
        $bindings = [
            'trueVal' => true,
            'string' => 'hello',
            'number' => 42,
            'array' => [1, 2, 3],
        ];

        $this->assertTrue($this->evaluator->evaluateBoolean('trueVal', $bindings));
        $this->assertTrue($this->evaluator->evaluateBoolean('string', $bindings));
        $this->assertTrue($this->evaluator->evaluateBoolean('number', $bindings));
        $this->assertTrue($this->evaluator->evaluateBoolean('array', $bindings));
    }

    public function testEvaluateBooleanFalsyValues(): void
    {
        $bindings = [
            'falseVal' => false,
            'emptyString' => '',
            'zero' => 0,
            'emptyArray' => [],
            'nullVal' => null,
        ];

        $this->assertFalse($this->evaluator->evaluateBoolean('falseVal', $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('emptyString', $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('zero', $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('emptyArray', $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('nullVal', $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('missing', $bindings));
    }

    public function testEvaluateBooleanWithComparison(): void
    {
        $bindings = ['age' => 25, 'name' => 'John'];

        $this->assertTrue($this->evaluator->evaluateBoolean('age > 18', $bindings));
        $this->assertTrue($this->evaluator->evaluateBoolean("name == 'John'", $bindings));
        $this->assertFalse($this->evaluator->evaluateBoolean('age > 30', $bindings));
    }
}
