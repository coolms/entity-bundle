<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection\Compiler;

use CoolMS\Entity\Bundle\DependencyInjection\Compiler\ExtrasInfrastructurePass;
use CoolMS\Entity\Doctrine\Mapping\ExtrasFieldMappingDriver;
use CoolMS\Entity\Doctrine\Mapping\TraitMappingDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The two mapping drivers decorate the metadata driver of the application's default entity manager, whatever its
 * name. A stock DoctrineBundle configuration names it `default`; an application may name it otherwise. With no
 * metadata driver at all the two are removed, and the build does not fail on a decorator of a missing service.
 */
final class TheMappingDriversDecorateTheDefaultManagersDriverTest extends TestCase
{
    #[Test]
    public function aStockConfigurationsDefaultManagerIsDecorated(): void
    {
        $container = $this->container('default');

        new ExtrasInfrastructurePass()->process($container);

        self::assertSame(
            ['doctrine.orm.default_metadata_driver', null, 0],
            $container->getDefinition(ExtrasFieldMappingDriver::class)->getDecoratedService(),
        );
        self::assertSame(
            ['doctrine.orm.default_metadata_driver', null, 10],
            $container->getDefinition(TraitMappingDriver::class)->getDecoratedService(),
        );

        // The decoration resolves. The manager's driver now names the chain's outermost decorator, which is the
        // lower priority: a higher priority is applied first, nearer the original.
        new DecoratorServicePass()->process($container);
        self::assertSame(
            ExtrasFieldMappingDriver::class,
            (string) $container->getAlias('doctrine.orm.default_metadata_driver'),
        );
    }

    #[Test]
    public function aManagerWithAnotherNameIsDecoratedByItsOwnName(): void
    {
        $container = $this->container('central');

        new ExtrasInfrastructurePass()->process($container);

        self::assertSame(
            'doctrine.orm.central_metadata_driver',
            $container->getDefinition(TraitMappingDriver::class)->getDecoratedService()[0] ?? null,
        );
    }

    #[Test]
    public function withNoMetadataDriverTheTwoAreRemovedAndNothingFails(): void
    {
        $container = $this->container(null);

        new ExtrasInfrastructurePass()->process($container);
        new DecoratorServicePass()->process($container);

        self::assertFalse($container->hasDefinition(ExtrasFieldMappingDriver::class));
        self::assertFalse($container->hasDefinition(TraitMappingDriver::class));
    }

    /**
     * The two drivers as the bundle's extension registers them, and a default manager whose metadata driver exists
     * (or none at all, for null).
     */
    private function container(?string $manager): ContainerBuilder
    {
        $container = new ContainerBuilder();
        if (null !== $manager) {
            $container->setParameter('doctrine.default_entity_manager', $manager);
            $container->register(sprintf('doctrine.orm.%s_metadata_driver', $manager), stdClass::class);
        }
        $container->register(ExtrasFieldMappingDriver::class);
        $container->register(TraitMappingDriver::class);

        return $container;
    }
}
