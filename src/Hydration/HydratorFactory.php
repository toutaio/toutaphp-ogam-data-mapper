<?php

declare(strict_types=1);

namespace Touta\Ogam\Hydration;

use Touta\Ogam\Contract\HydratorInterface;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Factory for creating hydrators based on hydration mode.
 */
final class HydratorFactory
{
    private ?ObjectHydrator $objectHydrator = null;

    private ?ArrayHydrator $arrayHydrator = null;

    private ?ScalarHydrator $scalarHydrator = null;

    public function __construct(
        private readonly TypeHandlerRegistry $typeHandlerRegistry,
        private readonly bool $mapUnderscoreToCamelCase = false,
    ) {}

    /**
     * Create a hydrator for the given hydration mode.
     */
    public function create(Hydration $hydration): HydratorInterface
    {
        return match ($hydration) {
            Hydration::OBJECT => $this->getObjectHydrator(),
            Hydration::ARRAY => $this->getArrayHydrator(),
            Hydration::SCALAR => $this->getScalarHydrator(),
        };
    }

    /**
     * Get the default hydrator (OBJECT mode).
     */
    public function getDefault(): HydratorInterface
    {
        return $this->getObjectHydrator();
    }

    private function getObjectHydrator(): ObjectHydrator
    {
        if ($this->objectHydrator === null) {
            $this->objectHydrator = new ObjectHydrator(
                $this->typeHandlerRegistry,
                $this->mapUnderscoreToCamelCase,
            );
        }

        return $this->objectHydrator;
    }

    private function getArrayHydrator(): ArrayHydrator
    {
        if ($this->arrayHydrator === null) {
            $this->arrayHydrator = new ArrayHydrator(
                $this->typeHandlerRegistry,
                $this->mapUnderscoreToCamelCase,
            );
        }

        return $this->arrayHydrator;
    }

    private function getScalarHydrator(): ScalarHydrator
    {
        if ($this->scalarHydrator === null) {
            $this->scalarHydrator = new ScalarHydrator($this->typeHandlerRegistry);
        }

        return $this->scalarHydrator;
    }
}
