<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Expression;

use ReflectionProperty;

/**
 * Safe expression evaluator for dynamic SQL conditions.
 *
 * Supports a restricted subset of PHP-like expressions:
 * - Property access: name, user.email
 * - Comparison operators: ==, ===, !=, !==, <, >, <=, >=
 * - Logical operators: &&, ||, !
 * - Literals: null, true, false, 'string', "string", integers, floats
 * - Parentheses for grouping
 *
 * Security: Does NOT use eval(). All expressions are tokenized and evaluated safely.
 */
final class ExpressionEvaluator
{
    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $position = 0;

    /**
     * Evaluate an expression and return the result.
     *
     * @param array<string, mixed> $bindings
     */
    public function evaluate(string $expression, array $bindings): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        // Security check first
        $this->validateSecurity($expression);

        // Tokenize
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;

        if ($this->tokens === []) {
            return null;
        }

        return $this->parseExpression($bindings);
    }

    /**
     * Evaluate an expression as a boolean value.
     *
     * @param array<string, mixed> $bindings
     */
    public function evaluateBoolean(string $expression, array $bindings): bool
    {
        $value = $this->evaluate($expression, $bindings);

        if (\is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (\is_string($value)) {
            return $value !== '';
        }

        if (\is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    /**
     * Validate that the expression doesn't contain dangerous constructs.
     *
     * @throws ExpressionSecurityException
     */
    private function validateSecurity(string $expression): void
    {
        // Check for shell execution (backticks)
        if (str_contains($expression, '`')) {
            throw ExpressionSecurityException::shellExecutionNotAllowed($expression);
        }

        // Check for variable variables (before global variables check)
        if (preg_match('/\$\$/', $expression)) {
            throw ExpressionSecurityException::variableVariablesNotAllowed($expression);
        }

        // Check for global variables
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SESSION|SERVER|ENV|FILES|GLOBALS)\b/', $expression)) {
            throw ExpressionSecurityException::globalVariablesNotAllowed($expression);
        }

        // Check for static access (before function calls to catch SomeClass::method() first)
        if (preg_match('/[a-zA-Z_][a-zA-Z0-9_]*::/', $expression)) {
            throw ExpressionSecurityException::staticAccessNotAllowed($expression);
        }

        // Check for include/require
        if (preg_match('/\b(include|include_once|require|require_once)\b/i', $expression)) {
            throw ExpressionSecurityException::includeRequireNotAllowed($expression);
        }

        // Check for new keyword (before function calls to catch "new Class()" first)
        if (preg_match('/\bnew\s+/i', $expression)) {
            throw ExpressionSecurityException::objectInstantiationNotAllowed($expression);
        }

        // Check for arrow functions (before generic function calls)
        if (preg_match('/\bfn\s*\(/', $expression)) {
            throw ExpressionSecurityException::anonymousFunctionsNotAllowed($expression);
        }

        // Check for closures/anonymous functions (before generic function calls)
        if (preg_match('/\bfunction\s*\(/', $expression)) {
            throw ExpressionSecurityException::anonymousFunctionsNotAllowed($expression);
        }

        // Check for method calls: ->method()
        if (preg_match('/->[\s]*[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $expression)) {
            throw ExpressionSecurityException::methodCallsNotAllowed($expression);
        }

        // Check for property method calls: obj.method()
        if (preg_match('/\.[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $expression)) {
            throw ExpressionSecurityException::methodCallsNotAllowed($expression);
        }

        // Check for dangerous function calls: word followed by (
        if (preg_match('/\b(exec|eval|system|shell_exec|passthru|popen|proc_open|assert|create_function|call_user_func|call_user_func_array|file_get_contents|file_put_contents|fopen|fwrite|unlink|rmdir|chmod|chown|preg_replace_callback|array_map|array_filter|array_walk|usort|uasort|uksort|count|strlen|sizeof|is_array|is_null|is_string|is_int|is_bool|is_float|gettype|isset|empty|print_r|var_dump|var_export)\s*\(/i', $expression)) {
            throw ExpressionSecurityException::functionCallsNotAllowed($expression);
        }

        // Check for any generic function call pattern: identifier(
        // But allow property access which looks like: property.method
        if (preg_match('/(?<!\.)(?<!\->)\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $expression)) {
            throw ExpressionSecurityException::functionCallsNotAllowed($expression);
        }

        // Check for assignments: = but not == or ===, != or !==, <= or >=
        if (preg_match('/(?<![=!<>])=(?!=)/', $expression)) {
            throw ExpressionSecurityException::assignmentsNotAllowed($expression);
        }

        // Check for compound assignments
        if (preg_match('/[+\-*\/%&|^]=/', $expression)) {
            throw ExpressionSecurityException::assignmentsNotAllowed($expression);
        }

        // Check for increment/decrement
        if (preg_match('/\+\+|--/', $expression)) {
            throw ExpressionSecurityException::incrementDecrementNotAllowed($expression);
        }

        // Check for array access
        if (preg_match('/\[/', $expression)) {
            throw ExpressionSecurityException::arrayAccessNotAllowed($expression);
        }
    }

    /**
     * Tokenize the expression into a list of tokens.
     *
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = \strlen($expression);
        $i = 0;

        while ($i < $length) {
            // Skip whitespace
            if (ctype_space($expression[$i])) {
                $i++;

                continue;
            }

            // Operators (multi-character first)
            if ($i + 2 < $length) {
                $three = substr($expression, $i, 3);

                if ($three === '===') {
                    $tokens[] = ['type' => 'operator', 'value' => '==='];
                    $i += 3;

                    continue;
                }

                if ($three === '!==') {
                    $tokens[] = ['type' => 'operator', 'value' => '!=='];
                    $i += 3;

                    continue;
                }
            }

            if ($i + 1 < $length) {
                $two = substr($expression, $i, 2);

                if (\in_array($two, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
                    $tokens[] = ['type' => 'operator', 'value' => $two];
                    $i += 2;

                    continue;
                }
            }

            // Single-character operators
            if (\in_array($expression[$i], ['<', '>', '!', '(', ')'], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $expression[$i]];
                $i++;

                continue;
            }

            // Negative numbers: - followed by digit (only at start or after operator)
            if ($expression[$i] === '-' && $i + 1 < $length && ctype_digit($expression[$i + 1])) {
                $lastToken = $tokens !== [] ? $tokens[\count($tokens) - 1] : null;

                if ($lastToken === null || $lastToken['type'] === 'operator') {
                    $numStart = $i;
                    $i++; // skip -

                    while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                        $i++;
                    }

                    $tokens[] = ['type' => 'number', 'value' => substr($expression, $numStart, $i - $numStart)];

                    continue;
                }
            }

            // Numbers
            if (ctype_digit($expression[$i])) {
                $start = $i;

                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $i++;
                }

                $tokens[] = ['type' => 'number', 'value' => substr($expression, $start, $i - $start)];

                continue;
            }

            // Strings (single-quoted)
            if ($expression[$i] === "'") {
                $i++; // skip opening quote
                $start = $i;
                $value = '';

                while ($i < $length && $expression[$i] !== "'") {
                    if ($expression[$i] === '\\' && $i + 1 < $length) {
                        $nextChar = $expression[$i + 1];

                        if ($nextChar === "'") {
                            $value .= "'";
                            $i += 2;

                            continue;
                        }

                        if ($nextChar === '\\') {
                            $value .= '\\';
                            $i += 2;

                            continue;
                        }
                    }

                    $value .= $expression[$i];
                    $i++;
                }

                $i++; // skip closing quote
                $tokens[] = ['type' => 'string', 'value' => $value];

                continue;
            }

            // Strings (double-quoted)
            if ($expression[$i] === '"') {
                $i++; // skip opening quote
                $value = '';

                while ($i < $length && $expression[$i] !== '"') {
                    if ($expression[$i] === '\\' && $i + 1 < $length) {
                        $nextChar = $expression[$i + 1];

                        if ($nextChar === '"') {
                            $value .= '"';
                            $i += 2;

                            continue;
                        }

                        if ($nextChar === '\\') {
                            $value .= '\\';
                            $i += 2;

                            continue;
                        }
                    }

                    $value .= $expression[$i];
                    $i++;
                }

                $i++; // skip closing quote
                $tokens[] = ['type' => 'string', 'value' => $value];

                continue;
            }

            // Identifiers (property names, keywords)
            if (ctype_alpha($expression[$i]) || $expression[$i] === '_' || $expression[$i] === '$') {
                $start = $i;

                // Skip leading $
                if ($expression[$i] === '$') {
                    $i++;
                }

                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_' || $expression[$i] === '.')) {
                    $i++;
                }

                $value = substr($expression, $start, $i - $start);

                // Remove leading $ if present
                if (str_starts_with($value, '$')) {
                    $value = substr($value, 1);
                }

                // Check for keywords
                $lower = strtolower($value);

                if ($lower === 'null') {
                    $tokens[] = ['type' => 'null', 'value' => 'null'];
                } elseif ($lower === 'true') {
                    $tokens[] = ['type' => 'boolean', 'value' => 'true'];
                } elseif ($lower === 'false') {
                    $tokens[] = ['type' => 'boolean', 'value' => 'false'];
                } else {
                    $tokens[] = ['type' => 'identifier', 'value' => $value];
                }

                continue;
            }

            // Unknown character, skip
            $i++;
        }

        return $tokens;
    }

    /**
     * Parse the expression (entry point).
     *
     * @param array<string, mixed> $bindings
     */
    private function parseExpression(array $bindings): mixed
    {
        return $this->parseOr($bindings);
    }

    /**
     * Parse OR expressions (lowest precedence).
     *
     * @param array<string, mixed> $bindings
     */
    private function parseOr(array $bindings): mixed
    {
        $left = $this->parseAnd($bindings);

        while ($this->match('||')) {
            $this->advance();
            $right = $this->parseAnd($bindings);
            $left = (bool) $left || (bool) $right;
        }

        return $left;
    }

    /**
     * Parse AND expressions.
     *
     * @param array<string, mixed> $bindings
     */
    private function parseAnd(array $bindings): mixed
    {
        $left = $this->parseComparison($bindings);

        while ($this->match('&&')) {
            $this->advance();
            $right = $this->parseComparison($bindings);
            $left = (bool) $left && (bool) $right;
        }

        return $left;
    }

    /**
     * Parse comparison expressions.
     *
     * @param array<string, mixed> $bindings
     */
    private function parseComparison(array $bindings): mixed
    {
        $left = $this->parseUnary($bindings);

        $token = $this->current();

        if ($token !== null && \in_array($token['value'], ['===', '!==', '==', '!=', '<=', '>=', '<', '>'], true)) {
            $operator = $token['value'];
            $this->advance();
            $right = $this->parseUnary($bindings);

            return $this->applyComparison($left, $operator, $right);
        }

        return $left;
    }

    /**
     * Parse unary expressions (!, -).
     *
     * @param array<string, mixed> $bindings
     */
    private function parseUnary(array $bindings): mixed
    {
        if ($this->match('!')) {
            $this->advance();

            return !$this->parseUnary($bindings);
        }

        return $this->parsePrimary($bindings);
    }

    /**
     * Parse primary expressions (literals, identifiers, parentheses).
     *
     * @param array<string, mixed> $bindings
     */
    private function parsePrimary(array $bindings): mixed
    {
        $token = $this->current();

        if ($token === null) {
            return null;
        }

        // Parentheses
        if ($token['type'] === 'operator' && $token['value'] === '(') {
            $this->advance();
            $result = $this->parseExpression($bindings);

            $closing = $this->current();
            if ($closing === null || $closing['type'] !== 'operator' || $closing['value'] !== ')') {
                throw new \InvalidArgumentException('Expected closing parenthesis ")" in expression.');
            }
            $this->advance(); // skip )

            return $result;
        }

        // Literals
        if ($token['type'] === 'null') {
            $this->advance();

            return null;
        }

        if ($token['type'] === 'boolean') {
            $this->advance();

            return $token['value'] === 'true';
        }

        if ($token['type'] === 'number') {
            $this->advance();

            return str_contains($token['value'], '.')
                ? (float) $token['value']
                : (int) $token['value'];
        }

        if ($token['type'] === 'string') {
            $this->advance();

            return $token['value'];
        }

        // Identifier (property access)
        if ($token['type'] === 'identifier') {
            $this->advance();

            return $this->resolveProperty($token['value'], $bindings);
        }

        return null;
    }

    /**
     * Apply a comparison operator.
     */
    private function applyComparison(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '===' => $left === $right,
            '!==' => $left !== $right,
            '==' => $left == $right,
            '!=' => $left != $right,
            '<' => $left < $right,
            '>' => $left > $right,
            '<=' => $left <= $right,
            '>=' => $left >= $right,
            default => false,
        };
    }

    /**
     * Resolve a property path from bindings.
     *
     * @param array<string, mixed> $bindings
     */
    private function resolveProperty(string $path, array $bindings): mixed
    {
        $parts = explode('.', $path);
        $current = $bindings;

        foreach ($parts as $part) {
            if (\is_array($current)) {
                if (!\array_key_exists($part, $current)) {
                    return null;
                }

                $current = $current[$part];
            } elseif (\is_object($current)) {
                $current = $this->getObjectProperty($current, $part);
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Get a property value from an object.
     */
    private function getObjectProperty(object $object, string $property): mixed
    {
        // Try getter
        $getter = 'get' . ucfirst($property);

        if (method_exists($object, $getter)) {
            return $object->{$getter}();
        }

        // Try boolean getter
        $isGetter = 'is' . ucfirst($property);

        if (method_exists($object, $isGetter)) {
            return $object->{$isGetter}();
        }

        // Try direct property
        if (property_exists($object, $property)) {
            $reflection = new ReflectionProperty($object, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue($object);
        }

        return null;
    }

    /**
     * Check if the current token matches the expected value.
     */
    private function match(string $value): bool
    {
        $token = $this->current();

        return $token !== null && $token['value'] === $value;
    }

    /**
     * Check if the current token matches any of the expected values.
     *
     * @param list<string> $values
     */
    private function matchAny(array $values): bool
    {
        $token = $this->current();

        return $token !== null && \in_array($token['value'], $values, true);
    }

    /**
     * Get the current token.
     *
     * @return array{type: string, value: string}|null
     */
    private function current(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }

    /**
     * Advance to the next token.
     */
    private function advance(): void
    {
        $this->position++;
    }
}
