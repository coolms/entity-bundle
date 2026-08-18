<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Tests\ApiPlatform\Resource\Provider;

use ApiPlatform\Metadata\Get;
use CoolMS\Entity\Field\EntityFieldDescriptorInterface;
use CoolMS\Entity\Field\FieldDescriptor;
use CoolMS\Entity\Filter\VirtualFieldDescriptor;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;
use CoolMS\Entity\VirtualField\VirtualFieldRegistryInterface;
use CoolMS\EntityBundle\ApiPlatform\Resource\EntityFieldsResource;
use CoolMS\EntityBundle\ApiPlatform\Resource\Provider\EntityFieldsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase X-2.5b -- provider surfaces stored AND virtual fields.
 */
final class EntityFieldsProviderTest extends TestCase
{
    public function testProvideReturnsStoredAndVirtualFields(): void
    {
        $storedField = new FieldDescriptor(
            field: 'username',
            label: 'Username',
            type: 'string',
            filterable: true,
            filterOperators: ['eq', 'cn'],
            sortable: true,
            searchable: true,
        );
        $virtualField = new VirtualFieldDescriptor(
            name: 'daysSinceLastLogin',
            label: 'Days since last login',
            filterType: 'int',
            sqlExpression: 'DATE_DIFF(CURRENT_DATE(), u.lastLoginAt)',
            allowedOps: ['lt', 'gt'],
        );
        $provider = $this->makeProvider(
            alias: 'user',
            fqcn: ProviderStubEntity::class,
            storedFields: [$storedField],
            virtualFields: [$virtualField],
        );

        $resource = $provider->provide(new Get(), ['alias' => 'user']);

        self::assertInstanceOf(EntityFieldsResource::class, $resource);
        self::assertSame('user', $resource->alias);
        self::assertSame('ProviderStubEntity', $resource->entityType);
        self::assertCount(1, $resource->fields);
        self::assertSame('username', $resource->fields[0]->field);
        self::assertCount(1, $resource->virtualFields);
        self::assertSame('daysSinceLastLogin', $resource->virtualFields[0]->name);
    }

    public function testProvideReturnsEmptyVirtualFieldsWhenNoneRegistered(): void
    {
        // Backward-compat assertion: existing consumers that ignore
        // the new `virtualFields` slot still see an unchanged
        // response shape -- the slot is present but empty.
        $provider = $this->makeProvider(
            alias: 'user',
            fqcn: ProviderStubEntity::class,
            storedFields: [],
            virtualFields: [],
        );

        $resource = $provider->provide(new Get(), ['alias' => 'user']);

        self::assertSame([], $resource->virtualFields);
    }

    public function testUnknownAliasYields404(): void
    {
        $provider = $this->makeProvider(
            alias: 'user',
            fqcn: ProviderStubEntity::class,
            storedFields: [],
            virtualFields: [],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Entity alias "unknown" is not registered.');

        $provider->provide(new Get(), ['alias' => 'unknown']);
    }

    /**
     * @param list<FieldDescriptor>        $storedFields
     * @param list<VirtualFieldDescriptor> $virtualFields
     */
    private function makeProvider(
        string $alias,
        string $fqcn,
        array $storedFields,
        array $virtualFields,
    ): EntityFieldsProvider {
        $aliasRegistry = $this->createStub(EntityAliasRegistryInterface::class);
        $aliasRegistry->method('resolve')->willReturnCallback(
            static fn (string $a): ?string => $a === $alias ? $fqcn : null,
        );

        $descriptor = $this->createStub(EntityFieldDescriptorInterface::class);
        $descriptor->method('describe')->willReturn($storedFields);

        $virtualRegistry = $this->createStub(VirtualFieldRegistryInterface::class);
        $virtualRegistry->method('getForEntity')->willReturnCallback(
            static fn (string $a): array => $a === $alias ? $virtualFields : [],
        );

        return new EntityFieldsProvider($aliasRegistry, $descriptor, $virtualRegistry);
    }
}

/**
 * Test-only stub whose short class name is asserted in the provider's
 * `entityType` field.
 */
final class ProviderStubEntity
{
}
