<?php

declare(strict_types=1);

namespace Touta\Ogam;

use Touta\Ogam\Contract\SessionFactoryInterface;
use Touta\Ogam\Parsing\XmlConfigurationParser;
use Touta\Ogam\Session\DefaultSessionFactory;

/**
 * Builder for creating SessionFactory instances.
 *
 * The main entry point for configuring and building Ogam.
 *
 * Usage:
 * ```php
 * $sessionFactory = (new SessionFactoryBuilder())
 *     ->withConfiguration('/path/to/ogam-config.xml')
 *     ->build();
 *
 * $session = $sessionFactory->openSession();
 * ```
 */
final class SessionFactoryBuilder
{
    private ?Configuration $configuration = null;

    private ?string $configurationPath = null;

    private ?string $configurationXml = null;

    /**
     * Build with an XML configuration file.
     *
     * @param string $path Path to the configuration XML file
     */
    public function withConfiguration(string $path): self
    {
        $this->configurationPath = $path;

        return $this;
    }

    /**
     * Build with an XML configuration string.
     *
     * @param string $xml The configuration XML content
     * @param string|null $basePath Base path for resolving relative paths
     */
    public function withXmlConfiguration(string $xml, ?string $basePath = null): self
    {
        $this->configurationXml = $xml;

        return $this;
    }

    /**
     * Build with a pre-built Configuration object.
     */
    public function withConfigurationObject(Configuration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * Build the SessionFactory.
     */
    public function build(): SessionFactoryInterface
    {
        $configuration = $this->getConfiguration();

        return new DefaultSessionFactory($configuration);
    }

    private function getConfiguration(): Configuration
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        if ($this->configurationPath !== null) {
            $parser = new XmlConfigurationParser();

            return $parser->parse($this->configurationPath);
        }

        if ($this->configurationXml !== null) {
            $parser = new XmlConfigurationParser();

            return $parser->parseXml($this->configurationXml);
        }

        throw new \RuntimeException(
            'No configuration provided. Call withConfiguration(), withXmlConfiguration(), or withConfigurationObject().',
        );
    }
}
