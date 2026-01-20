<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\Discriminator;

final class DiscriminatorTest extends TestCase
{
    public function testGetColumn(): void
    {
        $discriminator = new Discriminator('type');

        $this->assertSame('type', $discriminator->getColumn());
    }

    public function testGetPhpTypeDefault(): void
    {
        $discriminator = new Discriminator('type');

        $this->assertNull($discriminator->getPhpType());
    }

    public function testGetPhpTypeCustom(): void
    {
        $discriminator = new Discriminator('type', 'string');

        $this->assertSame('string', $discriminator->getPhpType());
    }

    public function testGetCasesDefault(): void
    {
        $discriminator = new Discriminator('type');

        $this->assertSame([], $discriminator->getCases());
    }

    public function testGetCasesCustom(): void
    {
        $cases = [
            'admin' => 'adminResultMap',
            'user' => 'userResultMap',
            'guest' => 'guestResultMap',
        ];

        $discriminator = new Discriminator('type', 'string', $cases);

        $this->assertSame($cases, $discriminator->getCases());
    }

    public function testGetResultMapIdExisting(): void
    {
        $discriminator = new Discriminator('type', 'string', [
            'admin' => 'adminResultMap',
            'user' => 'userResultMap',
        ]);

        $this->assertSame('adminResultMap', $discriminator->getResultMapId('admin'));
        $this->assertSame('userResultMap', $discriminator->getResultMapId('user'));
    }

    public function testGetResultMapIdNonExisting(): void
    {
        $discriminator = new Discriminator('type', 'string', [
            'admin' => 'adminResultMap',
        ]);

        $this->assertNull($discriminator->getResultMapId('guest'));
    }

    public function testGetResultMapIdWithEmptyCases(): void
    {
        $discriminator = new Discriminator('type');

        $this->assertNull($discriminator->getResultMapId('admin'));
    }

    public function testAllParametersCombined(): void
    {
        $cases = [
            '1' => 'enabledResultMap',
            '0' => 'disabledResultMap',
        ];

        $discriminator = new Discriminator(
            column: 'is_active',
            phpType: 'int',
            cases: $cases,
        );

        $this->assertSame('is_active', $discriminator->getColumn());
        $this->assertSame('int', $discriminator->getPhpType());
        $this->assertSame($cases, $discriminator->getCases());
        $this->assertSame('enabledResultMap', $discriminator->getResultMapId('1'));
        $this->assertSame('disabledResultMap', $discriminator->getResultMapId('0'));
    }
}
