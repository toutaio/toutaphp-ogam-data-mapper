<?php

declare(strict_types=1);

namespace Touta\Ogam\Type;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Touta\Ogam\Contract\TypeHandlerInterface;
use Touta\Ogam\Type\Handler\BooleanHandler;
use Touta\Ogam\Type\Handler\DateTimeHandler;
use Touta\Ogam\Type\Handler\DateTimeImmutableHandler;
use Touta\Ogam\Type\Handler\EnumHandler;
use Touta\Ogam\Type\Handler\FloatHandler;
use Touta\Ogam\Type\Handler\IntegerHandler;
use Touta\Ogam\Type\Handler\JsonHandler;
use Touta\Ogam\Type\Handler\StringHandler;
use UnitEnum;

/**
 * Registry for type handlers.
 *
 * Manages the mapping between PHP types and their handlers.
 * Provides auto-detection of handlers based on value type.
 */
final class TypeHandlerRegistry
{
    /** @var array<string, TypeHandlerInterface> */
    private array $handlers = [];

    /** @var array<string, TypeHandlerInterface> */
    private array $enumHandlers = [];

    private TypeHandlerInterface $unknownHandler;

    public function __construct()
    {
        $this->unknownHandler = new StringHandler();
        $this->registerDefaultHandlers();
    }

    /**
     * Register a type handler for a PHP type.
     *
     * @param string $phpType The PHP type (class name, 'int', 'string', etc.)
     * @param TypeHandlerInterface $handler The handler
     */
    public function register(string $phpType, TypeHandlerInterface $handler): void
    {
        $this->handlers[strtolower($phpType)] = $handler;
    }

    /**
     * Get a handler for a PHP type.
     *
     * @param string $phpType The PHP type
     *
     * @return TypeHandlerInterface The handler (falls back to unknown handler)
     */
    public function getHandler(string $phpType): TypeHandlerInterface
    {
        $normalizedType = strtolower($phpType);

        if (isset($this->handlers[$normalizedType])) {
            return $this->handlers[$normalizedType];
        }

        // Check if it's an enum
        if (enum_exists($phpType)) {
            return $this->getEnumHandler($phpType);
        }

        // Check if it's a subclass of a registered type
        foreach ($this->handlers as $registeredType => $handler) {
            if (class_exists($phpType) && class_exists($registeredType) && is_a($phpType, $registeredType, true)) {
                return $handler;
            }
        }

        return $this->unknownHandler;
    }

    /**
     * Get a handler for a value based on its runtime type.
     *
     * @param mixed $value The value
     *
     * @return TypeHandlerInterface The handler
     */
    public function getHandlerForValue(mixed $value): TypeHandlerInterface
    {
        if ($value === null) {
            return $this->unknownHandler;
        }

        $type = get_debug_type($value);

        // Handle specific object types
        if (\is_object($value)) {
            $class = $value::class;

            if ($value instanceof BackedEnum || $value instanceof UnitEnum) {
                return $this->getEnumHandler($class);
            }

            if ($value instanceof DateTimeImmutable) {
                return $this->handlers[strtolower(DateTimeImmutable::class)]
                    ?? $this->handlers[strtolower(DateTimeInterface::class)]
                    ?? $this->unknownHandler;
            }

            if ($value instanceof DateTimeInterface) {
                return $this->handlers[strtolower(DateTimeInterface::class)]
                    ?? $this->unknownHandler;
            }

            // Check for registered class handlers
            if (isset($this->handlers[strtolower($class)])) {
                return $this->handlers[strtolower($class)];
            }
        }

        return $this->handlers[strtolower($type)] ?? $this->unknownHandler;
    }

    /**
     * Check if a handler is registered for a type.
     *
     * @param string $phpType The PHP type
     */
    public function hasHandler(string $phpType): bool
    {
        $normalizedType = strtolower($phpType);

        if (isset($this->handlers[$normalizedType])) {
            return true;
        }

        if (enum_exists($phpType)) {
            return true;
        }

        return false;
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, TypeHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Set the handler used for unknown types.
     */
    public function setUnknownHandler(TypeHandlerInterface $handler): void
    {
        $this->unknownHandler = $handler;
    }

    /**
     * Get the handler for unknown types.
     */
    public function getUnknownHandler(): TypeHandlerInterface
    {
        return $this->unknownHandler;
    }

    /**
     * Get or create an enum handler for the given enum class.
     *
     * @param class-string $enumClass The enum class name
     */
    private function getEnumHandler(string $enumClass): TypeHandlerInterface
    {
        if (!isset($this->enumHandlers[$enumClass])) {
            $this->enumHandlers[$enumClass] = new EnumHandler($enumClass);
        }

        return $this->enumHandlers[$enumClass];
    }

    private function registerDefaultHandlers(): void
    {
        // Primitive types
        $this->register('int', new IntegerHandler());
        $this->register('integer', new IntegerHandler());
        $this->register('float', new FloatHandler());
        $this->register('double', new FloatHandler());
        $this->register('string', new StringHandler());
        $this->register('bool', new BooleanHandler());
        $this->register('boolean', new BooleanHandler());

        // Date/Time types
        $this->register(DateTimeInterface::class, new DateTimeHandler());
        $this->register(DateTime::class, new DateTimeHandler());
        $this->register(DateTimeImmutable::class, new DateTimeImmutableHandler());

        // JSON type
        $this->register('json', new JsonHandler());
        $this->register('array', new JsonHandler());
    }
}
