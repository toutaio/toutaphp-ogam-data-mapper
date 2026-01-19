<?php

declare(strict_types=1);

namespace Touta\Ogam\Session;

use BadMethodCallException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\SessionInterface;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;

/**
 * Proxy that implements a mapper interface by delegating to the session.
 *
 * This allows defining mapper interfaces and calling their methods
 * as if they were regular PHP objects, while the actual SQL execution
 * is handled by the session.
 *
 * @template T of object
 */
final class MapperProxy
{
    /** @var class-string<T> */
    private readonly string $mapperInterface;

    /** @var array<string, ReflectionMethod> */
    private array $methodCache = [];

    /**
     * @param class-string<T> $mapperInterface
     */
    public function __construct(
        private readonly SessionInterface $session,
        string $mapperInterface,
        private readonly Configuration $configuration,
    ) {
        if (!interface_exists($mapperInterface)) {
            throw new InvalidArgumentException(
                \sprintf('Mapper interface "%s" does not exist', $mapperInterface),
            );
        }

        $this->mapperInterface = $mapperInterface;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $method = $this->getMethod($name);
        $statementId = $this->getStatementId($name);
        $statement = $this->getMappedStatement($statementId);

        $parameter = $this->extractParameter($method, $arguments);

        return match ($statement->getType()) {
            StatementType::SELECT => $this->executeSelect($method, $statement, $parameter),
            StatementType::INSERT => $this->session->insert($statementId, $parameter),
            StatementType::UPDATE => $this->session->update($statementId, $parameter),
            StatementType::DELETE => $this->session->delete($statementId, $parameter),
            StatementType::CALLABLE => $this->session->selectList($statementId, $parameter),
        };
    }

    private function getMethod(string $name): ReflectionMethod
    {
        if (!isset($this->methodCache[$name])) {
            $reflection = new ReflectionClass($this->mapperInterface);

            if (!$reflection->hasMethod($name)) {
                throw new BadMethodCallException(
                    \sprintf(
                        'Method "%s" does not exist in mapper interface "%s"',
                        $name,
                        $this->mapperInterface,
                    ),
                );
            }

            $this->methodCache[$name] = $reflection->getMethod($name);
        }

        return $this->methodCache[$name];
    }

    private function getStatementId(string $methodName): string
    {
        return $this->mapperInterface . '.' . $methodName;
    }

    private function getMappedStatement(string $id): MappedStatement
    {
        $statement = $this->configuration->getMappedStatement($id);

        if ($statement === null) {
            throw new RuntimeException(
                \sprintf('No mapped statement found for "%s"', $id),
            );
        }

        return $statement;
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return array<string, mixed>|object|null
     */
    private function extractParameter(ReflectionMethod $method, array $arguments): array|object|null
    {
        $params = $method->getParameters();

        if (\count($params) === 0) {
            return null;
        }

        if (\count($params) === 1 && \count($arguments) === 1) {
            $arg = $arguments[0];

            // Single object parameter
            if (\is_object($arg)) {
                return $arg;
            }

            // Single array parameter - ensure string keys
            if (\is_array($arg)) {
                /** @var array<string, mixed> $arg */
                return $arg;
            }
        }

        // Map arguments to named parameters
        $result = [];

        foreach ($params as $index => $param) {
            if (isset($arguments[$index])) {
                $result[$param->getName()] = $arguments[$index];
            } elseif ($param->isDefaultValueAvailable()) {
                $result[$param->getName()] = $param->getDefaultValue();
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    private function executeSelect(
        ReflectionMethod $method,
        MappedStatement $statement,
        array|object|null $parameter,
    ): mixed {
        $returnType = $method->getReturnType();
        $statementId = $this->getStatementId($method->getName());

        // Determine if single or list result expected
        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();

            // Array return type means list
            if ($typeName === 'array') {
                return $this->session->selectList($statementId, $parameter);
            }

            // Iterable return type means cursor
            if ($typeName === 'iterable' || $typeName === 'Iterator' || $typeName === 'Generator') {
                return $this->session->selectCursor($statementId, $parameter);
            }

            // Nullable type or specific class means single
            if ($returnType->allowsNull() || !\in_array($typeName, ['int', 'string', 'float', 'bool'], true)) {
                return $this->session->selectOne($statementId, $parameter);
            }

            // Scalar type - use scalar hydration
            return $this->session->selectOne($statementId, $parameter, Hydration::SCALAR);
        }

        // Default to list
        return $this->session->selectList($statementId, $parameter);
    }
}
