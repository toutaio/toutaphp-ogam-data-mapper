<?php

declare(strict_types=1);

namespace Touta\Ogam\Parsing;

use DOMDocument;
use DOMElement;
use DOMNode;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\Association;
use Touta\Ogam\Mapping\Collection;
use Touta\Ogam\Mapping\Discriminator;
use Touta\Ogam\Mapping\FetchType;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\BindSqlNode;
use Touta\Ogam\Sql\Node\ChooseSqlNode;
use Touta\Ogam\Sql\Node\ForEachSqlNode;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\MixedSqlNode;
use Touta\Ogam\Sql\Node\SetSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Sql\Node\TrimSqlNode;
use Touta\Ogam\Sql\Node\WhereSqlNode;

/**
 * Parses XML mapper files into Configuration objects.
 */
final class XmlMapperParser
{
    public function __construct(
        private readonly Configuration $configuration,
    ) {}

    /**
     * Parse a mapper XML file.
     *
     * @param string $path Path to the XML file
     */
    public function parse(string $path): void
    {
        $xml = $this->loadXml($path);

        $mapper = $xml->documentElement;

        if ($mapper === null || $mapper->nodeName !== 'mapper') {
            throw new \RuntimeException('Invalid mapper file: root element must be <mapper>');
        }

        $namespace = $mapper->getAttribute('namespace');

        if ($namespace === '') {
            throw new \RuntimeException('Mapper must have a namespace attribute');
        }

        // Parse result maps first
        foreach ($this->getChildElements($mapper, 'resultMap') as $element) {
            $this->parseResultMap($element, $namespace);
        }

        // Parse statements
        $this->parseStatements($mapper, $namespace);
    }

    /**
     * Parse a mapper XML string.
     */
    public function parseXml(string $xml, string $resourcePath = ''): void
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;

        if (!@$doc->loadXML($xml)) {
            throw new \RuntimeException('Failed to parse XML');
        }

        $mapper = $doc->documentElement;

        if ($mapper === null || $mapper->nodeName !== 'mapper') {
            throw new \RuntimeException('Invalid mapper: root element must be <mapper>');
        }

        $namespace = $mapper->getAttribute('namespace');

        if ($namespace === '') {
            throw new \RuntimeException('Mapper must have a namespace attribute');
        }

        foreach ($this->getChildElements($mapper, 'resultMap') as $element) {
            $this->parseResultMap($element, $namespace);
        }

        $this->parseStatements($mapper, $namespace);
    }

    private function loadXml(string $path): DOMDocument
    {
        if (!\file_exists($path)) {
            throw new \RuntimeException(\sprintf('Mapper file not found: %s', $path));
        }

        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;

        if (!@$xml->load($path)) {
            throw new \RuntimeException(\sprintf('Failed to parse mapper file: %s', $path));
        }

        return $xml;
    }

    private function parseResultMap(DOMElement $element, string $namespace): void
    {
        $id = $element->getAttribute('id');
        $type = $this->configuration->resolveTypeAlias($element->getAttribute('type'));
        $extends = $element->getAttribute('extends') ?: null;
        $autoMapping = $element->getAttribute('autoMapping') !== 'false';

        $idMappings = [];
        $resultMappings = [];
        $associations = [];
        $collections = [];
        $discriminator = null;

        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            match ($child->nodeName) {
                'id' => $idMappings[] = $this->parseResultMapping($child),
                'result' => $resultMappings[] = $this->parseResultMapping($child),
                'association' => $associations[] = $this->parseAssociation($child, $namespace),
                'collection' => $collections[] = $this->parseCollection($child, $namespace),
                'discriminator' => $discriminator = $this->parseDiscriminator($child, $namespace),
                default => null,
            };
        }

        $resultMap = new ResultMap(
            $namespace . '.' . $id,
            $type,
            $idMappings,
            $resultMappings,
            $associations,
            $collections,
            $discriminator,
            $autoMapping,
            $extends !== null ? ($namespace . '.' . $extends) : null,
        );

        $this->configuration->addResultMap($resultMap);
    }

    private function parseResultMapping(DOMElement $element): ResultMapping
    {
        return new ResultMapping(
            $element->getAttribute('property'),
            $element->getAttribute('column'),
            $element->getAttribute('phpType') ?: null,
            $element->getAttribute('sqlType') ?: null,
            $element->getAttribute('typeHandler') ?: null,
        );
    }

    private function parseAssociation(DOMElement $element, string $namespace): Association
    {
        $property = $element->getAttribute('property');
        $column = $element->getAttribute('column') ?: null;
        $phpType = $this->configuration->resolveTypeAlias($element->getAttribute('phpType') ?: '');
        $resultMapId = $element->getAttribute('resultMap') ?: null;
        $fetchType = $this->parseFetchType($element->getAttribute('fetchType'));

        // Check for nested result map
        $nestedResultMappings = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && ($child->nodeName === 'id' || $child->nodeName === 'result')) {
                $nestedResultMappings[] = $this->parseResultMapping($child);
            }
        }

        return new Association(
            $property,
            $column,
            $phpType !== '' ? $phpType : null,
            $resultMapId !== null ? ($namespace . '.' . $resultMapId) : null,
            $fetchType,
            $nestedResultMappings,
        );
    }

    private function parseCollection(DOMElement $element, string $namespace): Collection
    {
        $property = $element->getAttribute('property');
        $column = $element->getAttribute('column') ?: null;
        $ofType = $this->configuration->resolveTypeAlias($element->getAttribute('ofType') ?: '');
        $resultMapId = $element->getAttribute('resultMap') ?: null;
        $fetchType = $this->parseFetchType($element->getAttribute('fetchType'));

        $nestedResultMappings = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && ($child->nodeName === 'id' || $child->nodeName === 'result')) {
                $nestedResultMappings[] = $this->parseResultMapping($child);
            }
        }

        return new Collection(
            $property,
            $column,
            $ofType !== '' ? $ofType : null,
            $resultMapId !== null ? ($namespace . '.' . $resultMapId) : null,
            $fetchType,
            $nestedResultMappings,
        );
    }

    private function parseDiscriminator(DOMElement $element, string $namespace): Discriminator
    {
        $column = $element->getAttribute('column');
        $phpType = $element->getAttribute('phpType') ?: null;

        $cases = [];

        foreach ($this->getChildElements($element, 'case') as $caseElement) {
            $value = $caseElement->getAttribute('value');
            $resultMapId = $caseElement->getAttribute('resultMap');
            $cases[$value] = $namespace . '.' . $resultMapId;
        }

        return new Discriminator($column, $cases, $phpType);
    }

    private function parseFetchType(string $value): FetchType
    {
        return match (\strtolower($value)) {
            'lazy' => FetchType::LAZY,
            default => FetchType::EAGER,
        };
    }

    private function parseStatements(DOMElement $mapper, string $namespace): void
    {
        $statementTypes = [
            'select' => StatementType::SELECT,
            'insert' => StatementType::INSERT,
            'update' => StatementType::UPDATE,
            'delete' => StatementType::DELETE,
        ];

        foreach ($statementTypes as $tagName => $type) {
            foreach ($this->getChildElements($mapper, $tagName) as $element) {
                $this->parseStatement($element, $namespace, $type);
            }
        }
    }

    private function parseStatement(DOMElement $element, string $namespace, StatementType $type): void
    {
        $id = $element->getAttribute('id');
        $resultMapId = $element->getAttribute('resultMap') ?: null;
        $resultType = $element->getAttribute('resultType') ?: null;
        $parameterType = $element->getAttribute('parameterType') ?: null;
        $useGeneratedKeys = $element->getAttribute('useGeneratedKeys') === 'true';
        $keyProperty = $element->getAttribute('keyProperty') ?: null;
        $keyColumn = $element->getAttribute('keyColumn') ?: null;
        $timeout = (int) ($element->getAttribute('timeout') ?: 0);
        $fetchSize = (int) ($element->getAttribute('fetchSize') ?: 0);
        $hydration = $this->parseHydration($element->getAttribute('hydration'));

        // Resolve type aliases
        if ($resultType !== null) {
            $resultType = $this->configuration->resolveTypeAlias($resultType);
        }

        if ($parameterType !== null) {
            $parameterType = $this->configuration->resolveTypeAlias($parameterType);
        }

        // Parse SQL content
        $sqlSource = $this->parseSqlNodes($element);

        $statement = new MappedStatement(
            $id,
            $namespace,
            $type,
            null, // SQL is in sqlSource
            $resultMapId !== null ? ($namespace . '.' . $resultMapId) : null,
            $resultType,
            $parameterType,
            $useGeneratedKeys,
            $keyProperty,
            $keyColumn,
            $timeout,
            $fetchSize,
            $hydration,
            $sqlSource,
        );

        $this->configuration->addMappedStatement($statement);
    }

    private function parseHydration(?string $value): ?Hydration
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (\strtolower($value)) {
            'array' => Hydration::ARRAY,
            'scalar' => Hydration::SCALAR,
            default => Hydration::OBJECT,
        };
    }

    private function parseSqlNodes(DOMElement $element): DynamicSqlSource
    {
        $nodes = $this->parseNodeChildren($element);
        $rootNode = \count($nodes) === 1 ? $nodes[0] : new MixedSqlNode($nodes);

        return new DynamicSqlSource($this->configuration, $rootNode);
    }

    /**
     * @return list<SqlNode>
     */
    private function parseNodeChildren(DOMElement $element): array
    {
        $nodes = [];

        foreach ($element->childNodes as $child) {
            $node = $this->parseNode($child);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function parseNode(DOMNode $node): ?SqlNode
    {
        if ($node instanceof \DOMText) {
            $text = \trim($node->textContent);

            if ($text === '') {
                return null;
            }

            return new TextSqlNode(' ' . $text . ' ');
        }

        if (!$node instanceof DOMElement) {
            return null;
        }

        return match ($node->nodeName) {
            'if' => $this->parseIfNode($node),
            'choose' => $this->parseChooseNode($node),
            'foreach' => $this->parseForEachNode($node),
            'where' => $this->parseWhereNode($node),
            'set' => $this->parseSetNode($node),
            'trim' => $this->parseTrimNode($node),
            'bind' => $this->parseBindNode($node),
            default => null,
        };
    }

    private function parseIfNode(DOMElement $element): IfSqlNode
    {
        $test = $element->getAttribute('test');
        $children = $this->parseNodeChildren($element);
        $contents = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);

        return new IfSqlNode($test, $contents);
    }

    private function parseChooseNode(DOMElement $element): ChooseSqlNode
    {
        $whenNodes = [];
        $otherwise = null;

        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($child->nodeName === 'when') {
                $whenNodes[] = $this->parseIfNode($child);
            } elseif ($child->nodeName === 'otherwise') {
                $children = $this->parseNodeChildren($child);
                $otherwise = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);
            }
        }

        return new ChooseSqlNode($whenNodes, $otherwise);
    }

    private function parseForEachNode(DOMElement $element): ForEachSqlNode
    {
        $collection = $element->getAttribute('collection');
        $item = $element->getAttribute('item') ?: 'item';
        $index = $element->getAttribute('index') ?: null;
        $open = $element->getAttribute('open') ?: '';
        $close = $element->getAttribute('close') ?: '';
        $separator = $element->getAttribute('separator') ?: '';

        $children = $this->parseNodeChildren($element);
        $contents = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);

        return new ForEachSqlNode($collection, $item, $index, $contents, $open, $close, $separator);
    }

    private function parseWhereNode(DOMElement $element): WhereSqlNode
    {
        $children = $this->parseNodeChildren($element);
        $contents = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);

        return new WhereSqlNode($contents);
    }

    private function parseSetNode(DOMElement $element): SetSqlNode
    {
        $children = $this->parseNodeChildren($element);
        $contents = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);

        return new SetSqlNode($contents);
    }

    private function parseTrimNode(DOMElement $element): TrimSqlNode
    {
        $prefix = $element->getAttribute('prefix') ?: '';
        $prefixOverrides = $element->getAttribute('prefixOverrides') ?: '';
        $suffix = $element->getAttribute('suffix') ?: '';
        $suffixOverrides = $element->getAttribute('suffixOverrides') ?: '';

        $children = $this->parseNodeChildren($element);
        $contents = \count($children) === 1 ? $children[0] : new MixedSqlNode($children);

        return new TrimSqlNode($contents, $prefix, $prefixOverrides, $suffix, $suffixOverrides);
    }

    private function parseBindNode(DOMElement $element): BindSqlNode
    {
        $name = $element->getAttribute('name');
        $value = $element->getAttribute('value');

        return new BindSqlNode($name, $value);
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
