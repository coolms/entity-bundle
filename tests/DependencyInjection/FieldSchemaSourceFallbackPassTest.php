<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Entity\Bundle\DependencyInjection\Compiler\FieldSchemaSourceFallbackPass;
use CoolMS\Entity\Bundle\Field\NoRuntimeFields;
use CoolMS\Entity\Contract\FieldMetadataSourceInterface;
use CoolMS\Entity\Contract\FieldSchemaSourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FieldSchemaSourceFallbackPassTest extends TestCase
{
    #[Test]
    public function withNoFieldPackageBothPortsGetTheFallback(): void
    {
        $container = new ContainerBuilder();

        new FieldSchemaSourceFallbackPass()->process($container);

        self::assertSame(NoRuntimeFields::class, (string) $container->getAlias(FieldSchemaSourceInterface::class));
        self::assertSame(NoRuntimeFields::class, (string) $container->getAlias(FieldMetadataSourceInterface::class));
        self::assertSame(NoRuntimeFields::class, $container->getDefinition(NoRuntimeFields::class)->getClass());
    }

    #[Test]
    public function aPortAnotherPackageBoundKeepsItsBinding(): void
    {
        $container = new ContainerBuilder();
        $container->register('field.schema_source', 'Field\SchemaSource');
        $container->setAlias(FieldSchemaSourceInterface::class, 'field.schema_source');
        $container->setAlias(FieldMetadataSourceInterface::class, 'field.schema_source');

        new FieldSchemaSourceFallbackPass()->process($container);

        self::assertSame('field.schema_source', (string) $container->getAlias(FieldSchemaSourceInterface::class));
        self::assertSame('field.schema_source', (string) $container->getAlias(FieldMetadataSourceInterface::class));
        self::assertFalse($container->hasDefinition(NoRuntimeFields::class), 'no fallback registered beside it');
    }

    #[Test]
    public function theFallbackAnswersNothingForEveryAlias(): void
    {
        $none = new NoRuntimeFields();

        self::assertSame([], $none->getRuntimeFields('product'));
        self::assertSame([], $none->getRuntimeFieldSummaries('product'));
        self::assertSame([], $none->getResolvedFieldMetadata(self::class, 'product'));
        self::assertFalse($none->hasFileOverride('product', 'colour'));
    }
}
