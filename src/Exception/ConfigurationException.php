<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

/**
 * Thrown when there's an error in configuration.
 */
final class ConfigurationException extends OgamException
{
    public static function missingEnvironment(string $id): self
    {
        return new self(\sprintf('Environment "%s" not found', $id));
    }

    public static function missingMapper(string $class): self
    {
        return new self(\sprintf('Mapper "%s" not found', $class));
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self(\sprintf('Invalid configuration: %s', $message));
    }
}
