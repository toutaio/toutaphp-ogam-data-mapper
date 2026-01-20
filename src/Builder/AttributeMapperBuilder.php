<?php

declare(strict_types=1);

namespace Touta\Ogam\Builder;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use Touta\Ogam\Attribute\Delete;
use Touta\Ogam\Attribute\Insert;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Options;
use Touta\Ogam\Attribute\Param;
use Touta\Ogam\Attribute\Result;
use Touta\Ogam\Attribute\Results;
use Touta\Ogam\Attribute\Select;
use Touta\Ogam\Attribute\Update;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\TextSqlNode;

/**
 * Builds mapper configuration from PHP attributes.
 *
 * Parses interfaces annotated with #[Mapper] and methods annotated
 * with #[Select], #[Insert], #[Update], #[Delete] attributes.
 */
final class AttributeMapperBuilder
{
    public function __construct(
        private readonly Configuration $configuration,
    ) {}

    /**
     * Parse a mapper interface and register its statements.
     *
     * @param class-string $mapperInterface The interface to parse
     *
     * @throws InvalidArgumentException if the class is not a valid mapper interface
     */
    public function parse(string $mapperInterface): void
    {
        $reflection = new ReflectionClass($mapperInterface);

        if (!$reflection->isInterface()) {
            throw new InvalidArgumentException(
                \sprintf('%s must be an interface', $mapperInterface),
            );
        }

        $mapperAttribute = $this->getMapperAttribute($reflection);

        if ($mapperAttribute === null) {
            throw new InvalidArgumentException(
                \sprintf('%s must have #[Mapper] attribute', $mapperInterface),
            );
        }

        $namespace = $mapperAttribute->namespace ?? $mapperInterface;

        $this->configuration->addMapper($mapperInterface);

        foreach ($reflection->getMethods() as $method) {
            $this->parseMethod($method, $namespace);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function getMapperAttribute(ReflectionClass $reflection): ?Mapper
    {
        $attributes = $reflection->getAttributes(Mapper::class);

        if (\count($attributes) === 0) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function parseMethod(ReflectionMethod $method, string $namespace): void
    {
        $statementInfo = $this->getStatementInfo($method);

        if ($statementInfo === null) {
            return;
        }

        [$statementType, $sql, $resultMap, $resultType, $timeout, $flushCache] = $statementInfo;

        // Get options attribute if present
        $options = $this->getOptionsAttribute($method);

        // Build result map from Result/Results attributes if present
        $resultMapId = $resultMap;

        if ($resultMapId === null) {
            $resultMappings = $this->getResultMappings($method);

            if (!empty($resultMappings)) {
                $resultMapId = $namespace . '.' . $method->getName() . 'ResultMap';
                $this->createResultMap($resultMapId, $resultMappings);
            }
        }

        // Create SQL source
        $sqlSource = new DynamicSqlSource(
            $this->configuration,
            new TextSqlNode($sql),
        );

        // Resolve options values (options may be null)
        if ($options !== null) {
            $useGeneratedKeys = $options->useGeneratedKeys;
            $keyProperty = $options->keyProperty;
            $keyColumn = $options->keyColumn;
            $statementTimeout = $options->timeout ?? $timeout;
            $fetchSizeValue = $options->fetchSize ?? 0;
            $flushCacheValue = $options->flushCache;
            $useCacheValue = $options->useCache;
        } else {
            $useGeneratedKeys = false;
            $keyProperty = null;
            $keyColumn = null;
            $statementTimeout = $timeout;
            $fetchSizeValue = 0;
            $flushCacheValue = $flushCache;
            $useCacheValue = true;
        }

        $statement = new MappedStatement(
            $method->getName(),
            $namespace,
            $statementType,
            null,
            $resultMapId,
            $resultType,
            null,
            $useGeneratedKeys,
            $keyProperty,
            $keyColumn,
            $statementTimeout,
            $fetchSizeValue,
            null,
            $sqlSource,
            $flushCacheValue,
            $useCacheValue,
        );

        $this->configuration->addMappedStatement($statement);
    }

    /**
     * @return array{StatementType, string, string|null, string|null, int, bool}|null
     */
    private function getStatementInfo(ReflectionMethod $method): ?array
    {
        // Check for Select
        $selectAttrs = $method->getAttributes(Select::class);

        if (\count($selectAttrs) > 0) {
            $select = $selectAttrs[0]->newInstance();

            return [
                StatementType::SELECT,
                $select->sql,
                $select->resultMap,
                $select->resultType,
                $select->timeout,
                false,
            ];
        }

        // Check for Insert
        $insertAttrs = $method->getAttributes(Insert::class);

        if (\count($insertAttrs) > 0) {
            $insert = $insertAttrs[0]->newInstance();

            return [
                StatementType::INSERT,
                $insert->sql,
                null,
                null,
                $insert->timeout,
                $insert->flushCache,
            ];
        }

        // Check for Update
        $updateAttrs = $method->getAttributes(Update::class);

        if (\count($updateAttrs) > 0) {
            $update = $updateAttrs[0]->newInstance();

            return [
                StatementType::UPDATE,
                $update->sql,
                null,
                null,
                $update->timeout,
                $update->flushCache,
            ];
        }

        // Check for Delete
        $deleteAttrs = $method->getAttributes(Delete::class);

        if (\count($deleteAttrs) > 0) {
            $delete = $deleteAttrs[0]->newInstance();

            return [
                StatementType::DELETE,
                $delete->sql,
                null,
                null,
                $delete->timeout,
                $delete->flushCache,
            ];
        }

        return null;
    }

    private function getOptionsAttribute(ReflectionMethod $method): ?Options
    {
        $attributes = $method->getAttributes(Options::class);

        if (\count($attributes) === 0) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @return list<ResultMapping>
     */
    private function getResultMappings(ReflectionMethod $method): array
    {
        $mappings = [];

        // Check for Results attribute first
        $resultsAttrs = $method->getAttributes(Results::class);

        if (\count($resultsAttrs) > 0) {
            $results = $resultsAttrs[0]->newInstance();

            foreach ($results->value as $result) {
                $mappings[] = new ResultMapping(
                    $result->property,
                    $result->column,
                    $result->phpType,
                    $result->typeHandler,
                );
            }

            return $mappings;
        }

        // Check for individual Result attributes
        $resultAttrs = $method->getAttributes(Result::class);

        foreach ($resultAttrs as $attr) {
            $result = $attr->newInstance();
            $mappings[] = new ResultMapping(
                $result->property,
                $result->column,
                $result->phpType,
                $result->typeHandler,
            );
        }

        return $mappings;
    }

    /**
     * @param list<ResultMapping> $mappings
     */
    private function createResultMap(string $id, array $mappings): void
    {
        $resultMap = new ResultMap(
            $id,
            'object',
            [],
            $mappings,
            [],
            [],
            null,
            true,
            null,
        );

        $this->configuration->addResultMap($resultMap);
    }
}
