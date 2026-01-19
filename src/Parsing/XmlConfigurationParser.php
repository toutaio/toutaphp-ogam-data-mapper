<?php

declare(strict_types=1);

namespace Touta\Ogam\Parsing;

use DOMDocument;
use DOMElement;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\PooledDataSource;
use Touta\Ogam\DataSource\SimpleDataSource;
use Touta\Ogam\DataSource\UnpooledDataSource;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Transaction\JdbcTransactionFactory;
use Touta\Ogam\Transaction\ManagedTransactionFactory;

/**
 * Parses the main Ogam configuration XML file.
 */
final class XmlConfigurationParser
{
    private Configuration $configuration;

    private ?string $basePath = null;

    public function __construct()
    {
        $this->configuration = new Configuration();
    }

    /**
     * Parse configuration from a file path.
     */
    public function parse(string $path): Configuration
    {
        $this->basePath = \dirname($path);

        $xml = $this->loadXml($path);
        $root = $xml->documentElement;

        if ($root === null || $root->nodeName !== 'configuration') {
            throw new RuntimeException('Invalid configuration: root element must be <configuration>');
        }

        $this->parseConfiguration($root);

        return $this->configuration;
    }

    /**
     * Parse configuration from XML string.
     */
    public function parseXml(string $xml, ?string $basePath = null): Configuration
    {
        $this->basePath = $basePath;

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;

        if (!@$doc->loadXML($xml)) {
            throw new RuntimeException('Failed to parse configuration XML');
        }

        $root = $doc->documentElement;

        if ($root === null || $root->nodeName !== 'configuration') {
            throw new RuntimeException('Invalid configuration: root element must be <configuration>');
        }

        $this->parseConfiguration($root);

        return $this->configuration;
    }

    private function loadXml(string $path): DOMDocument
    {
        if (!file_exists($path)) {
            throw new RuntimeException(\sprintf('Configuration file not found: %s', $path));
        }

        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;

        if (!@$xml->load($path)) {
            throw new RuntimeException(\sprintf('Failed to parse configuration file: %s', $path));
        }

        return $xml;
    }

    private function parseConfiguration(DOMElement $root): void
    {
        // Parse settings
        $settings = $this->getFirstChildElement($root, 'settings');

        if ($settings !== null) {
            $this->parseSettings($settings);
        }

        // Parse type aliases
        $typeAliases = $this->getFirstChildElement($root, 'typeAliases');

        if ($typeAliases !== null) {
            $this->parseTypeAliases($typeAliases);
        }

        // Parse type handlers
        $typeHandlers = $this->getFirstChildElement($root, 'typeHandlers');

        if ($typeHandlers !== null) {
            $this->parseTypeHandlers($typeHandlers);
        }

        // Parse environments
        $environments = $this->getFirstChildElement($root, 'environments');

        if ($environments !== null) {
            $this->parseEnvironments($environments);
        }

        // Parse mappers
        $mappers = $this->getFirstChildElement($root, 'mappers');

        if ($mappers !== null) {
            $this->parseMappers($mappers);
        }
    }

    private function parseSettings(DOMElement $element): void
    {
        foreach ($this->getChildElements($element, 'setting') as $setting) {
            $name = $setting->getAttribute('name');
            $value = $setting->getAttribute('value');

            match ($name) {
                'cacheEnabled' => $this->configuration->setCacheEnabled($this->toBool($value)),
                'lazyLoadingEnabled' => $this->configuration->setLazyLoadingEnabled($this->toBool($value)),
                'mapUnderscoreToCamelCase' => $this->configuration->setMapUnderscoreToCamelCase($this->toBool($value)),
                'defaultExecutorType' => $this->configuration->setDefaultExecutorType($this->toExecutorType($value)),
                'defaultStatementTimeout' => $this->configuration->setDefaultStatementTimeout((int) $value),
                'useGeneratedKeys' => $this->configuration->setUseGeneratedKeys($this->toBool($value)),
                'debugMode' => $this->configuration->setDebugMode($this->toBool($value)),
                default => null, // Ignore unknown settings
            };
        }
    }

    private function parseTypeAliases(DOMElement $element): void
    {
        // Parse packages
        foreach ($this->getChildElements($element, 'package') as $package) {
            $name = $package->getAttribute('name');
            // In PHP, we can't scan packages, but we can register the namespace
            // This is a placeholder for future implementation
        }

        // Parse individual aliases
        foreach ($this->getChildElements($element, 'typeAlias') as $alias) {
            $aliasName = $alias->getAttribute('alias');
            $type = $alias->getAttribute('type');

            $this->configuration->addTypeAlias($aliasName, $type);
        }
    }

    private function parseTypeHandlers(DOMElement $element): void
    {
        foreach ($this->getChildElements($element, 'typeHandler') as $handler) {
            $phpType = $handler->getAttribute('phpType');
            $handlerClass = $handler->getAttribute('handler');

            if ($phpType !== '' && $handlerClass !== '' && class_exists($handlerClass)) {
                $handlerInstance = new $handlerClass();

                if ($handlerInstance instanceof \Touta\Ogam\Contract\TypeHandlerInterface) {
                    $this->configuration->addTypeHandler($phpType, $handlerInstance);
                }
            }
        }
    }

    private function parseEnvironments(DOMElement $element): void
    {
        $defaultEnv = $element->getAttribute('default');

        if ($defaultEnv !== '') {
            $this->configuration->setDefaultEnvironment($defaultEnv);
        }

        foreach ($this->getChildElements($element, 'environment') as $env) {
            $this->parseEnvironment($env);
        }
    }

    private function parseEnvironment(DOMElement $element): void
    {
        $id = $element->getAttribute('id');

        // Parse transaction manager
        $transactionManager = $this->getFirstChildElement($element, 'transactionManager');
        $transactionFactory = $this->parseTransactionManager($transactionManager);

        // Parse data source
        $dataSourceElement = $this->getFirstChildElement($element, 'dataSource');
        $dataSource = $this->parseDataSource($dataSourceElement);

        $environment = new Environment($id, $dataSource, $transactionFactory);

        $this->configuration->addEnvironment($environment);
    }

    private function parseTransactionManager(?DOMElement $element): ManagedTransactionFactory|JdbcTransactionFactory
    {
        if ($element === null) {
            return new ManagedTransactionFactory();
        }

        $type = strtoupper($element->getAttribute('type'));

        return match ($type) {
            'JDBC' => new JdbcTransactionFactory(),
            default => new ManagedTransactionFactory(),
        };
    }

    private function parseDataSource(?DOMElement $element): SimpleDataSource|PooledDataSource|UnpooledDataSource
    {
        if ($element === null) {
            throw new RuntimeException('Environment must have a dataSource');
        }

        $type = strtoupper($element->getAttribute('type'));

        $properties = [];

        foreach ($this->getChildElements($element, 'property') as $property) {
            $name = $property->getAttribute('name');
            $value = $property->getAttribute('value');
            $properties[$name] = $this->resolveProperty($value);
        }

        $dsn = $properties['dsn'] ?? $this->buildDsn($properties);
        $username = $properties['username'] ?? null;
        $password = $properties['password'] ?? null;

        return match ($type) {
            'POOLED' => new PooledDataSource($dsn, $username, $password),
            'UNPOOLED' => new UnpooledDataSource($dsn, $username, $password),
            default => new SimpleDataSource($dsn, $username, $password),
        };
    }

    /**
     * @param array<string, string> $properties
     */
    private function buildDsn(array $properties): string
    {
        $driver = $properties['driver'] ?? 'mysql';
        $host = $properties['host'] ?? 'localhost';
        $port = $properties['port'] ?? match ($driver) {
            'mysql' => '3306',
            'pgsql' => '5432',
            default => '',
        };
        $database = $properties['database'] ?? $properties['dbname'] ?? '';

        return match ($driver) {
            'mysql' => \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            'pgsql' => \sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            'sqlite' => \sprintf('sqlite:%s', $database),
            default => throw new RuntimeException(\sprintf('Unknown driver: %s', $driver)),
        };
    }

    private function resolveProperty(string $value): string
    {
        // Support environment variable interpolation: ${ENV_VAR}
        return (string) preg_replace_callback(
            '/\$\{([^}]+)\}/',
            static fn(array $m) => getenv($m[1]) ?: $m[0],
            $value,
        );
    }

    private function parseMappers(DOMElement $element): void
    {
        $mapperParser = new XmlMapperParser($this->configuration);

        foreach ($this->getChildElements($element, 'mapper') as $mapper) {
            $resource = $mapper->getAttribute('resource');

            if ($resource !== '') {
                $path = $this->resolvePath($resource);
                $mapperParser->parse($path);
            }

            $class = $mapper->getAttribute('class');

            if ($class !== '' && (class_exists($class) || interface_exists($class))) {
                /** @var class-string $class */
                $this->configuration->addMapper($class);
            }
        }

        // Parse package elements
        foreach ($this->getChildElements($element, 'package') as $package) {
            $name = $package->getAttribute('name');
            // In PHP, we would need to scan the directory for mapper files
            // This is a placeholder for future implementation
        }
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        if ($this->basePath !== null) {
            return $this->basePath . '/' . $path;
        }

        return $path;
    }

    private function toBool(string $value): bool
    {
        return \in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    private function toExecutorType(string $value): ExecutorType
    {
        return match (strtoupper($value)) {
            'REUSE' => ExecutorType::REUSE,
            'BATCH' => ExecutorType::BATCH,
            default => ExecutorType::SIMPLE,
        };
    }

    private function getFirstChildElement(DOMElement $parent, string $tagName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === $tagName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private function getChildElements(DOMElement $parent, string $tagName): array
    {
        $elements = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === $tagName) {
                $elements[] = $child;
            }
        }

        return $elements;
    }
}
