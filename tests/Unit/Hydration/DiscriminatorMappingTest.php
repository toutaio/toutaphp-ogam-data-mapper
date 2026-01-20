<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Hydration\ObjectHydrator;
use Touta\Ogam\Mapping\Discriminator;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

// Base class for polymorphic mapping
abstract class Vehicle
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}

final class Car extends Vehicle
{
    public function __construct(
        int $id,
        string $name,
        public readonly int $numberOfDoors,
    ) {
        parent::__construct($id, $name);
    }
}

final class Truck extends Vehicle
{
    public function __construct(
        int $id,
        string $name,
        public readonly float $loadCapacity,
    ) {
        parent::__construct($id, $name);
    }
}

final class Motorcycle extends Vehicle
{
    public function __construct(
        int $id,
        string $name,
        public readonly bool $hasSidecar = false,
    ) {
        parent::__construct($id, $name);
    }
}

// For testing discriminator with string enum-like values
abstract class Notification
{
    public function __construct(
        public readonly int $id,
        public readonly string $message,
    ) {}
}

final class EmailNotification extends Notification
{
    public function __construct(
        int $id,
        string $message,
        public readonly string $emailAddress,
    ) {
        parent::__construct($id, $message);
    }
}

final class SmsNotification extends Notification
{
    public function __construct(
        int $id,
        string $message,
        public readonly string $phoneNumber,
    ) {
        parent::__construct($id, $message);
    }
}

final class DiscriminatorMappingTest extends TestCase
{
    private ObjectHydrator $hydrator;

    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->hydrator = new ObjectHydrator(new TypeHandlerRegistry());
        $this->configuration = new Configuration();
    }

    public function testHydrateWithDiscriminatorSelectsCar(): void
    {
        // Register result maps for each vehicle type
        $carResultMap = new ResultMap(
            id: 'carMap',
            type: Car::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('numberOfDoors', 'number_of_doors', 'int'),
            ],
            autoMapping: false,
        );

        $truckResultMap = new ResultMap(
            id: 'truckMap',
            type: Truck::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('loadCapacity', 'load_capacity', 'float'),
            ],
            autoMapping: false,
        );

        $motorcycleResultMap = new ResultMap(
            id: 'motorcycleMap',
            type: Motorcycle::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('hasSidecar', 'has_sidecar', 'bool'),
            ],
            autoMapping: false,
        );

        $this->configuration->addResultMap($carResultMap);
        $this->configuration->addResultMap($truckResultMap);
        $this->configuration->addResultMap($motorcycleResultMap);

        // Main result map with discriminator
        $vehicleResultMap = new ResultMap(
            id: 'vehicleMap',
            type: Vehicle::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'vehicle_type',
                phpType: 'string',
                cases: [
                    'car' => 'carMap',
                    'truck' => 'truckMap',
                    'motorcycle' => 'motorcycleMap',
                ],
            ),
            autoMapping: false,
        );

        $row = [
            'id' => '1',
            'name' => 'Honda Civic',
            'vehicle_type' => 'car',
            'number_of_doors' => '4',
        ];

        $result = $this->hydrator->hydrateWithDiscriminator($row, $vehicleResultMap, $this->configuration);

        $this->assertInstanceOf(Car::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Honda Civic', $result->name);
        $this->assertSame(4, $result->numberOfDoors);
    }

    public function testHydrateWithDiscriminatorSelectsTruck(): void
    {
        $truckResultMap = new ResultMap(
            id: 'truckMap',
            type: Truck::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('loadCapacity', 'load_capacity', 'float'),
            ],
            autoMapping: false,
        );

        $this->configuration->addResultMap($truckResultMap);

        $vehicleResultMap = new ResultMap(
            id: 'vehicleMap',
            type: Vehicle::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'vehicle_type',
                phpType: 'string',
                cases: [
                    'truck' => 'truckMap',
                ],
            ),
            autoMapping: false,
        );

        $row = [
            'id' => '2',
            'name' => 'Ford F-150',
            'vehicle_type' => 'truck',
            'load_capacity' => '1500.5',
        ];

        $result = $this->hydrator->hydrateWithDiscriminator($row, $vehicleResultMap, $this->configuration);

        $this->assertInstanceOf(Truck::class, $result);
        $this->assertSame(2, $result->id);
        $this->assertSame('Ford F-150', $result->name);
        $this->assertSame(1500.5, $result->loadCapacity);
    }

    public function testHydrateWithDiscriminatorUsesBaseTypeWhenNoMatch(): void
    {
        $vehicleResultMap = new ResultMap(
            id: 'vehicleMap',
            type: Motorcycle::class, // Use a concrete type as fallback
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'vehicle_type',
                phpType: 'string',
                cases: [
                    'car' => 'carMap', // Not registered
                ],
            ),
            autoMapping: false,
        );

        $row = [
            'id' => '3',
            'name' => 'Unknown Vehicle',
            'vehicle_type' => 'unknown', // No matching case
        ];

        // Should fall back to base result map type
        $result = $this->hydrator->hydrateWithDiscriminator($row, $vehicleResultMap, $this->configuration);

        $this->assertInstanceOf(Motorcycle::class, $result);
        $this->assertSame(3, $result->id);
        $this->assertSame('Unknown Vehicle', $result->name);
    }

    public function testHydrateAllWithDiscriminator(): void
    {
        $carResultMap = new ResultMap(
            id: 'carMap',
            type: Car::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('numberOfDoors', 'number_of_doors', 'int'),
            ],
            autoMapping: false,
        );

        $truckResultMap = new ResultMap(
            id: 'truckMap',
            type: Truck::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('loadCapacity', 'load_capacity', 'float'),
            ],
            autoMapping: false,
        );

        $this->configuration->addResultMap($carResultMap);
        $this->configuration->addResultMap($truckResultMap);

        $vehicleResultMap = new ResultMap(
            id: 'vehicleMap',
            type: Vehicle::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'vehicle_type',
                phpType: 'string',
                cases: [
                    'car' => 'carMap',
                    'truck' => 'truckMap',
                ],
            ),
            autoMapping: false,
        );

        $rows = [
            [
                'id' => '1',
                'name' => 'Honda Civic',
                'vehicle_type' => 'car',
                'number_of_doors' => '4',
            ],
            [
                'id' => '2',
                'name' => 'Ford F-150',
                'vehicle_type' => 'truck',
                'load_capacity' => '1500.5',
            ],
            [
                'id' => '3',
                'name' => 'BMW M3',
                'vehicle_type' => 'car',
                'number_of_doors' => '2',
            ],
        ];

        $results = $this->hydrator->hydrateAllWithDiscriminator($rows, $vehicleResultMap, $this->configuration);

        $this->assertCount(3, $results);

        $this->assertInstanceOf(Car::class, $results[0]);
        $this->assertSame(4, $results[0]->numberOfDoors);

        $this->assertInstanceOf(Truck::class, $results[1]);
        $this->assertSame(1500.5, $results[1]->loadCapacity);

        $this->assertInstanceOf(Car::class, $results[2]);
        $this->assertSame(2, $results[2]->numberOfDoors);
    }

    public function testHydrateWithDiscriminatorAndNullColumn(): void
    {
        $vehicleResultMap = new ResultMap(
            id: 'vehicleMap',
            type: Motorcycle::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'vehicle_type',
                phpType: 'string',
                cases: [
                    'car' => 'carMap',
                ],
            ),
            autoMapping: false,
        );

        $row = [
            'id' => '1',
            'name' => 'Mystery Vehicle',
            'vehicle_type' => null, // Null discriminator column
        ];

        // Should fall back to base result map type
        $result = $this->hydrator->hydrateWithDiscriminator($row, $vehicleResultMap, $this->configuration);

        $this->assertInstanceOf(Motorcycle::class, $result);
    }

    public function testHydrateWithDiscriminatorStringEnumValues(): void
    {
        $emailResultMap = new ResultMap(
            id: 'emailMap',
            type: EmailNotification::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('message', 'message', 'string'),
                new ResultMapping('emailAddress', 'email_address', 'string'),
            ],
            autoMapping: false,
        );

        $smsResultMap = new ResultMap(
            id: 'smsMap',
            type: SmsNotification::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('message', 'message', 'string'),
                new ResultMapping('phoneNumber', 'phone_number', 'string'),
            ],
            autoMapping: false,
        );

        $this->configuration->addResultMap($emailResultMap);
        $this->configuration->addResultMap($smsResultMap);

        $notificationResultMap = new ResultMap(
            id: 'notificationMap',
            type: Notification::class,
            idMappings: [new ResultMapping('id', 'id', 'int')],
            resultMappings: [
                new ResultMapping('message', 'message', 'string'),
            ],
            discriminator: new Discriminator(
                column: 'notification_type',
                phpType: 'string',
                cases: [
                    'EMAIL' => 'emailMap',
                    'SMS' => 'smsMap',
                ],
            ),
            autoMapping: false,
        );

        $row = [
            'id' => '1',
            'message' => 'Hello!',
            'notification_type' => 'EMAIL',
            'email_address' => 'test@example.com',
        ];

        $result = $this->hydrator->hydrateWithDiscriminator($row, $notificationResultMap, $this->configuration);

        $this->assertInstanceOf(EmailNotification::class, $result);
        $this->assertSame('test@example.com', $result->emailAddress);
    }
}
