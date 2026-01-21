<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Hydration\ObjectHydrator;
use Touta\Ogam\Mapping\Association;
use Touta\Ogam\Mapping\Collection;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

// Test entities for nested result mapping
final class Author
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public ?AuthorProfile $profile = null,
        /** @var list<Post> */
        public array $posts = [],
    ) {}
}

final class AuthorProfile
{
    public function __construct(
        public readonly int $id,
        public readonly string $bio,
        public ?string $avatarUrl = null,
    ) {}
}

final class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public ?string $content = null,
    ) {}
}

final class Order
{
    public function __construct(
        public readonly int $id,
        public readonly string $orderNumber,
        public ?Customer $customer = null,
        /** @var list<OrderItem> */
        public array $items = [],
    ) {}
}

final class Customer
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public ?string $email = null,
    ) {}
}

final class OrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $productName,
        public readonly int $quantity,
        public readonly float $price,
    ) {}
}

final class NestedResultMappingTest extends TestCase
{
    private ObjectHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ObjectHydrator(new TypeHandlerRegistry());
    }

    public function testHydrateWithAssociation(): void
    {
        // Define a result map with an association (has-one relationship)
        $resultMap = new ResultMap(
            id: 'authorWithProfile',
            type: Author::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            associations: [
                new Association(
                    property: 'profile',
                    phpType: AuthorProfile::class,
                    resultMapId: null,
                    columnPrefix: 'profile_',
                    idMappings: [
                        new ResultMapping('id', 'profile_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('bio', 'profile_bio', 'string'),
                        new ResultMapping('avatarUrl', 'profile_avatar_url', 'string'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        $row = [
            'id' => '1',
            'name' => 'John Doe',
            'profile_id' => '10',
            'profile_bio' => 'A great author',
            'profile_avatar_url' => 'https://example.com/avatar.jpg',
        ];

        $result = $this->hydrator->hydrate($row, $resultMap, Author::class);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John Doe', $result->name);

        $this->assertInstanceOf(AuthorProfile::class, $result->profile);
        $this->assertSame(10, $result->profile->id);
        $this->assertSame('A great author', $result->profile->bio);
        $this->assertSame('https://example.com/avatar.jpg', $result->profile->avatarUrl);
    }

    public function testHydrateWithNullAssociation(): void
    {
        $resultMap = new ResultMap(
            id: 'authorWithProfile',
            type: Author::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            associations: [
                new Association(
                    property: 'profile',
                    phpType: AuthorProfile::class,
                    resultMapId: null,
                    columnPrefix: 'profile_',
                    idMappings: [
                        new ResultMapping('id', 'profile_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('bio', 'profile_bio', 'string'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        // Row with NULL association (e.g., LEFT JOIN where related record doesn't exist)
        $row = [
            'id' => '1',
            'name' => 'John Doe',
            'profile_id' => null,
            'profile_bio' => null,
        ];

        $result = $this->hydrator->hydrate($row, $resultMap, Author::class);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertNull($result->profile);
    }

    public function testHydrateAllWithCollection(): void
    {
        $resultMap = new ResultMap(
            id: 'authorWithPosts',
            type: Author::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            collections: [
                new Collection(
                    property: 'posts',
                    ofType: Post::class,
                    resultMapId: null,
                    columnPrefix: 'post_',
                    idMappings: [
                        new ResultMapping('id', 'post_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('title', 'post_title', 'string'),
                        new ResultMapping('content', 'post_content', 'string'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        // Multiple rows from a JOIN query
        $rows = [
            [
                'id' => '1',
                'name' => 'John Doe',
                'post_id' => '100',
                'post_title' => 'First Post',
                'post_content' => 'Content of first post',
            ],
            [
                'id' => '1',
                'name' => 'John Doe',
                'post_id' => '101',
                'post_title' => 'Second Post',
                'post_content' => 'Content of second post',
            ],
            [
                'id' => '2',
                'name' => 'Jane Smith',
                'post_id' => '102',
                'post_title' => 'Jane\'s Post',
                'post_content' => 'Jane\'s content',
            ],
        ];

        $results = $this->hydrator->hydrateAll($rows, $resultMap, Author::class);

        // Should group by author ID and create collections
        $this->assertCount(2, $results);

        // First author
        $author1 = $results[0];
        $this->assertInstanceOf(Author::class, $author1);
        $this->assertSame(1, $author1->id);
        $this->assertSame('John Doe', $author1->name);
        $this->assertCount(2, $author1->posts);

        $this->assertSame(100, $author1->posts[0]->id);
        $this->assertSame('First Post', $author1->posts[0]->title);
        $this->assertSame(101, $author1->posts[1]->id);
        $this->assertSame('Second Post', $author1->posts[1]->title);

        // Second author
        $author2 = $results[1];
        $this->assertSame(2, $author2->id);
        $this->assertSame('Jane Smith', $author2->name);
        $this->assertCount(1, $author2->posts);
        $this->assertSame(102, $author2->posts[0]->id);
    }

    public function testHydrateAllWithEmptyCollection(): void
    {
        $resultMap = new ResultMap(
            id: 'authorWithPosts',
            type: Author::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            collections: [
                new Collection(
                    property: 'posts',
                    ofType: Post::class,
                    resultMapId: null,
                    columnPrefix: 'post_',
                    idMappings: [
                        new ResultMapping('id', 'post_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('title', 'post_title', 'string'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        // Row with NULL collection items (e.g., LEFT JOIN where no related records exist)
        $rows = [
            [
                'id' => '1',
                'name' => 'John Doe',
                'post_id' => null,
                'post_title' => null,
            ],
        ];

        $results = $this->hydrator->hydrateAll($rows, $resultMap, Author::class);

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->posts);
    }

    public function testHydrateWithAssociationAndCollection(): void
    {
        $resultMap = new ResultMap(
            id: 'orderComplete',
            type: Order::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('orderNumber', 'order_number', 'string'),
            ],
            associations: [
                new Association(
                    property: 'customer',
                    phpType: Customer::class,
                    resultMapId: null,
                    columnPrefix: 'customer_',
                    idMappings: [
                        new ResultMapping('id', 'customer_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('name', 'customer_name', 'string'),
                        new ResultMapping('email', 'customer_email', 'string'),
                    ],
                ),
            ],
            collections: [
                new Collection(
                    property: 'items',
                    ofType: OrderItem::class,
                    resultMapId: null,
                    columnPrefix: 'item_',
                    idMappings: [
                        new ResultMapping('id', 'item_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('productName', 'item_product_name', 'string'),
                        new ResultMapping('quantity', 'item_quantity', 'int'),
                        new ResultMapping('price', 'item_price', 'float'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        $rows = [
            [
                'id' => '1',
                'order_number' => 'ORD-001',
                'customer_id' => '10',
                'customer_name' => 'Alice',
                'customer_email' => 'alice@example.com',
                'item_id' => '100',
                'item_product_name' => 'Widget A',
                'item_quantity' => '2',
                'item_price' => '29.99',
            ],
            [
                'id' => '1',
                'order_number' => 'ORD-001',
                'customer_id' => '10',
                'customer_name' => 'Alice',
                'customer_email' => 'alice@example.com',
                'item_id' => '101',
                'item_product_name' => 'Widget B',
                'item_quantity' => '1',
                'item_price' => '49.99',
            ],
        ];

        $results = $this->hydrator->hydrateAll($rows, $resultMap, Order::class);

        $this->assertCount(1, $results);

        $order = $results[0];
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(1, $order->id);
        $this->assertSame('ORD-001', $order->orderNumber);

        // Check association
        $this->assertInstanceOf(Customer::class, $order->customer);
        $this->assertSame(10, $order->customer->id);
        $this->assertSame('Alice', $order->customer->name);

        // Check collection
        $this->assertCount(2, $order->items);
        $this->assertSame('Widget A', $order->items[0]->productName);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame('Widget B', $order->items[1]->productName);
        $this->assertSame(1, $order->items[1]->quantity);
    }

    public function testHydrateWithDuplicateCollectionItems(): void
    {
        $resultMap = new ResultMap(
            id: 'authorWithPosts',
            type: Author::class,
            idMappings: [
                new ResultMapping('id', 'id', 'int'),
            ],
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            collections: [
                new Collection(
                    property: 'posts',
                    ofType: Post::class,
                    resultMapId: null,
                    columnPrefix: 'post_',
                    idMappings: [
                        new ResultMapping('id', 'post_id', 'int'),
                    ],
                    resultMappings: [
                        new ResultMapping('title', 'post_title', 'string'),
                    ],
                ),
            ],
            autoMapping: false,
        );

        // Rows with duplicate collection items (could happen with multiple JOINs)
        $rows = [
            [
                'id' => '1',
                'name' => 'John Doe',
                'post_id' => '100',
                'post_title' => 'First Post',
            ],
            [
                'id' => '1',
                'name' => 'John Doe',
                'post_id' => '100', // Same post ID - should not be duplicated
                'post_title' => 'First Post',
            ],
        ];

        $results = $this->hydrator->hydrateAll($rows, $resultMap, Author::class);

        $this->assertCount(1, $results);
        $this->assertCount(1, $results[0]->posts); // Should only have one post, not duplicated
    }
}
