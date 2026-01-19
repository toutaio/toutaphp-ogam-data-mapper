<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Mapping\ParameterMode;

/**
 * Builds a BoundSql from SQL with parameter placeholders.
 *
 * Converts #{property} and ${property} placeholders to positional parameters.
 */
final class SqlSourceBuilder
{
    private const PARAMETER_PATTERN = '/
        \#\{                           # #{
            \s*
            (?<property>[a-zA-Z_][a-zA-Z0-9_.]*)  # property name
            (?:\s*,\s*
                (?<attrs>[^}]+)         # optional attributes
            )?
            \s*
        \}                              # }
    /x';

    private const STRING_SUBSTITUTION_PATTERN = '/
        \$\{                           # ${
            \s*
            (?<property>[a-zA-Z_][a-zA-Z0-9_.]*)  # property name
            \s*
        \}                              # }
    /x';

    public function __construct(
        private readonly Configuration $configuration,
    ) {}

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    public function parse(string $sql, array|object|null $parameter): BoundSql
    {
        // First, handle string substitutions (${...})
        $sql = $this->substituteStrings($sql, $parameter);

        // Then, handle parameter bindings (#{...})
        $parameterMappings = [];
        $sql = $this->parseParameters($sql, $parameterMappings);

        return new BoundSql($sql, $parameterMappings);
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    private function substituteStrings(string $sql, array|object|null $parameter): string
    {
        return (string) \preg_replace_callback(
            self::STRING_SUBSTITUTION_PATTERN,
            function (array $matches) use ($parameter): string {
                $property = $matches['property'];
                $value = $this->getPropertyValue($parameter, $property);

                // Direct string substitution (be careful - SQL injection risk)
                return $value !== null ? (string) $value : '';
            },
            $sql,
        );
    }

    /**
     * @param list<ParameterMapping> $parameterMappings
     */
    private function parseParameters(string $sql, array &$parameterMappings): string
    {
        return (string) \preg_replace_callback(
            self::PARAMETER_PATTERN,
            function (array $matches) use (&$parameterMappings): string {
                $property = $matches['property'];
                $attrs = isset($matches['attrs']) ? $this->parseAttributes($matches['attrs']) : [];

                $parameterMappings[] = new ParameterMapping(
                    $property,
                    $attrs['phpType'] ?? null,
                    $attrs['sqlType'] ?? null,
                    $this->parseMode($attrs['mode'] ?? null),
                    $attrs['typeHandler'] ?? null,
                );

                return '?';
            },
            $sql,
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attrs): array
    {
        $result = [];
        $pairs = \preg_split('/\s*,\s*/', \trim($attrs));

        foreach ($pairs as $pair) {
            if (\preg_match('/^(\w+)\s*=\s*(.+)$/', \trim($pair), $m)) {
                $result[$m[1]] = \trim($m[2]);
            }
        }

        return $result;
    }

    private function parseMode(?string $mode): ParameterMode
    {
        if ($mode === null) {
            return ParameterMode::IN;
        }

        return match (\strtoupper($mode)) {
            'IN' => ParameterMode::IN,
            'OUT' => ParameterMode::OUT,
            'INOUT' => ParameterMode::INOUT,
            default => ParameterMode::IN,
        };
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    private function getPropertyValue(array|object|null $parameter, string $property): mixed
    {
        if ($parameter === null) {
            return null;
        }

        $parts = \explode('.', $property);
        $current = $parameter;

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

    private function getObjectProperty(object $object, string $property): mixed
    {
        $getter = 'get' . \ucfirst($property);

        if (\method_exists($object, $getter)) {
            return $object->{$getter}();
        }

        $isGetter = 'is' . \ucfirst($property);

        if (\method_exists($object, $isGetter)) {
            return $object->{$isGetter}();
        }

        if (\property_exists($object, $property)) {
            $reflection = new \ReflectionProperty($object, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue($object);
        }

        return null;
    }
}
