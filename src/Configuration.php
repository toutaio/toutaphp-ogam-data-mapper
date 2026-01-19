<?php

declare(strict_types=1);

namespace Touta\Ogam;

use Touta\Ogam\Contract\TypeHandlerInterface;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Central configuration for Ogam.
 *
 * Holds all settings, registered mappers, type handlers,
 * environments, and other configuration.
 */
final class Configuration
{
    // Settings
    private bool $cacheEnabled = true;
    private bool $lazyLoadingEnabled = false;
    private bool $mapUnderscoreToCamelCase = false;
    private ExecutorType $defaultExecutorType = ExecutorType::SIMPLE;
    private int $defaultStatementTimeout = 0;
    private bool $useGeneratedKeys = false;
    private string $defaultEnvironment = 'default';
    private bool $debugMode = false;

    /** @var array<string, Environment> */
    private array $environments = [];

    /** @var array<string, string> Type aliases (alias => class) */
    private array $typeAliases = [];

    /** @var array<string, MappedStatement> */
    private array $mappedStatements = [];

    /** @var array<string, ResultMap> */
    private array $resultMaps = [];

    /** @var list<string> Registered mapper interfaces */
    private array $mapperInterfaces = [];

    private TypeHandlerRegistry $typeHandlerRegistry;

    public function __construct()
    {
        $this->typeHandlerRegistry = new TypeHandlerRegistry();
        $this->registerDefaultTypeAliases();
    }

    // Settings getters and setters

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function setCacheEnabled(bool $cacheEnabled): self
    {
        $this->cacheEnabled = $cacheEnabled;

        return $this;
    }

    public function isLazyLoadingEnabled(): bool
    {
        return $this->lazyLoadingEnabled;
    }

    public function setLazyLoadingEnabled(bool $lazyLoadingEnabled): self
    {
        $this->lazyLoadingEnabled = $lazyLoadingEnabled;

        return $this;
    }

    public function isMapUnderscoreToCamelCase(): bool
    {
        return $this->mapUnderscoreToCamelCase;
    }

    public function setMapUnderscoreToCamelCase(bool $mapUnderscoreToCamelCase): self
    {
        $this->mapUnderscoreToCamelCase = $mapUnderscoreToCamelCase;

        return $this;
    }

    public function getDefaultExecutorType(): ExecutorType
    {
        return $this->defaultExecutorType;
    }

    public function setDefaultExecutorType(ExecutorType $defaultExecutorType): self
    {
        $this->defaultExecutorType = $defaultExecutorType;

        return $this;
    }

    public function getDefaultStatementTimeout(): int
    {
        return $this->defaultStatementTimeout;
    }

    public function setDefaultStatementTimeout(int $defaultStatementTimeout): self
    {
        $this->defaultStatementTimeout = $defaultStatementTimeout;

        return $this;
    }

    public function isUseGeneratedKeys(): bool
    {
        return $this->useGeneratedKeys;
    }

    public function setUseGeneratedKeys(bool $useGeneratedKeys): self
    {
        $this->useGeneratedKeys = $useGeneratedKeys;

        return $this;
    }

    public function getDefaultEnvironment(): string
    {
        return $this->defaultEnvironment;
    }

    public function setDefaultEnvironment(string $defaultEnvironment): self
    {
        $this->defaultEnvironment = $defaultEnvironment;

        return $this;
    }

    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function setDebugMode(bool $debugMode): self
    {
        $this->debugMode = $debugMode;

        return $this;
    }

    // Environment management

    public function addEnvironment(Environment $environment): self
    {
        $this->environments[$environment->getId()] = $environment;

        return $this;
    }

    public function getEnvironment(?string $id = null): ?Environment
    {
        $id ??= $this->defaultEnvironment;

        return $this->environments[$id] ?? null;
    }

    /**
     * @return array<string, Environment>
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    // Type alias management

    public function addTypeAlias(string $alias, string $type): self
    {
        $this->typeAliases[\strtolower($alias)] = $type;

        return $this;
    }

    public function resolveTypeAlias(string $alias): string
    {
        return $this->typeAliases[\strtolower($alias)] ?? $alias;
    }

    /**
     * @return array<string, string>
     */
    public function getTypeAliases(): array
    {
        return $this->typeAliases;
    }

    // Mapped statement management

    public function addMappedStatement(MappedStatement $statement): self
    {
        $this->mappedStatements[$statement->getFullId()] = $statement;

        return $this;
    }

    public function getMappedStatement(string $id): ?MappedStatement
    {
        return $this->mappedStatements[$id] ?? null;
    }

    public function hasMappedStatement(string $id): bool
    {
        return isset($this->mappedStatements[$id]);
    }

    /**
     * @return array<string, MappedStatement>
     */
    public function getMappedStatements(): array
    {
        return $this->mappedStatements;
    }

    // Result map management

    public function addResultMap(ResultMap $resultMap): self
    {
        $this->resultMaps[$resultMap->getId()] = $resultMap;

        return $this;
    }

    public function getResultMap(string $id): ?ResultMap
    {
        return $this->resultMaps[$id] ?? null;
    }

    /**
     * @return array<string, ResultMap>
     */
    public function getResultMaps(): array
    {
        return $this->resultMaps;
    }

    // Mapper management

    /**
     * @param class-string $mapperInterface
     */
    public function addMapper(string $mapperInterface): self
    {
        if (!\in_array($mapperInterface, $this->mapperInterfaces, true)) {
            $this->mapperInterfaces[] = $mapperInterface;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMapperInterfaces(): array
    {
        return $this->mapperInterfaces;
    }

    /**
     * @param class-string $mapperInterface
     */
    public function hasMapper(string $mapperInterface): bool
    {
        return \in_array($mapperInterface, $this->mapperInterfaces, true);
    }

    // Type handler management

    public function getTypeHandlerRegistry(): TypeHandlerRegistry
    {
        return $this->typeHandlerRegistry;
    }

    public function addTypeHandler(string $phpType, TypeHandlerInterface $handler): self
    {
        $this->typeHandlerRegistry->register($phpType, $handler);

        return $this;
    }

    private function registerDefaultTypeAliases(): void
    {
        // Primitive types
        $this->addTypeAlias('string', 'string');
        $this->addTypeAlias('int', 'int');
        $this->addTypeAlias('integer', 'int');
        $this->addTypeAlias('float', 'float');
        $this->addTypeAlias('double', 'float');
        $this->addTypeAlias('bool', 'bool');
        $this->addTypeAlias('boolean', 'bool');
        $this->addTypeAlias('array', 'array');
        $this->addTypeAlias('object', 'object');

        // Common classes
        $this->addTypeAlias('date', \DateTimeInterface::class);
        $this->addTypeAlias('datetime', \DateTimeInterface::class);
        $this->addTypeAlias('datetimeimmutable', \DateTimeImmutable::class);
    }
}
