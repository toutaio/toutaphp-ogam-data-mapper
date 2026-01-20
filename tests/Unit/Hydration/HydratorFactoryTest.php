<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Hydration\ArrayHydrator;
use Touta\Ogam\Hydration\HydratorFactory;
use Touta\Ogam\Hydration\ObjectHydrator;
use Touta\Ogam\Hydration\ScalarHydrator;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Type\TypeHandlerRegistry;

final class HydratorFactoryTest extends TestCase
{
    private HydratorFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new HydratorFactory(new TypeHandlerRegistry());
    }

    public function testCreateObjectHydrator(): void
    {
        $hydrator = $this->factory->create(Hydration::OBJECT);

        $this->assertInstanceOf(ObjectHydrator::class, $hydrator);
    }

    public function testCreateArrayHydrator(): void
    {
        $hydrator = $this->factory->create(Hydration::ARRAY);

        $this->assertInstanceOf(ArrayHydrator::class, $hydrator);
    }

    public function testCreateScalarHydrator(): void
    {
        $hydrator = $this->factory->create(Hydration::SCALAR);

        $this->assertInstanceOf(ScalarHydrator::class, $hydrator);
    }

    public function testGetDefaultReturnsObjectHydrator(): void
    {
        $hydrator = $this->factory->getDefault();

        $this->assertInstanceOf(ObjectHydrator::class, $hydrator);
    }

    public function testHydratorInstancesAreCached(): void
    {
        $hydrator1 = $this->factory->create(Hydration::OBJECT);
        $hydrator2 = $this->factory->create(Hydration::OBJECT);

        $this->assertSame($hydrator1, $hydrator2);
    }

    public function testArrayHydratorInstancesAreCached(): void
    {
        $hydrator1 = $this->factory->create(Hydration::ARRAY);
        $hydrator2 = $this->factory->create(Hydration::ARRAY);

        $this->assertSame($hydrator1, $hydrator2);
    }

    public function testScalarHydratorInstancesAreCached(): void
    {
        $hydrator1 = $this->factory->create(Hydration::SCALAR);
        $hydrator2 = $this->factory->create(Hydration::SCALAR);

        $this->assertSame($hydrator1, $hydrator2);
    }

    public function testDefaultReturnsSameAsCreateObject(): void
    {
        $default = $this->factory->getDefault();
        $object = $this->factory->create(Hydration::OBJECT);

        $this->assertSame($default, $object);
    }

    public function testFactoryWithCamelCaseMapping(): void
    {
        $factory = new HydratorFactory(new TypeHandlerRegistry(), mapUnderscoreToCamelCase: true);

        $hydrator = $factory->create(Hydration::ARRAY);
        $row = ['user_name' => 'John'];
        $result = $hydrator->hydrate($row, null, null);

        $this->assertSame(['userName' => 'John'], $result);
    }
}
