<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Entity\Bundle\Factory\EntityFactory;
use CoolMS\Entity\Bundle\Factory\EntityFactoryFactory;
use CoolMS\Entity\Bundle\Tests\Fixture\CatalogExtension;
use CoolMS\Entity\Bundle\Tests\Fixture\CategoryInterface;
use CoolMS\Entity\Bundle\Tests\Fixture\ProductInterface;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Serializer\Serializer;

/**
 * EntityFactoryRegistrationTrait is for the DI extension of a module that
 * ships entities: one call registers an entity factory for each entity class
 * the module owns.
 *
 * Each module gets one service locator of its own, holding an inline
 * EntityFactory definition per entity class and tagged `coolms.entity_factory`
 * once per class. This bundle collects every such tag, indexed by its `entity`
 * attribute, into the locator behind EntityFactoryFactoryInterface.
 *
 * The module's locator is registered autoconfigured, and that is load-bearing:
 * the framework's autoconfiguration tags every ServiceLocator
 * `container.service_locator`, and only that tag turns the inline definitions
 * into the lazy factories a locator needs. The compiled case below registers
 * the same rule. The fixture is the shape a consumer writes.
 */
final class EntityFactoryRegistrationTraitTest extends TestCase
{
    private const string LOCATOR = 'coolms.catalog.entity_factory_locator';

    #[Test]
    public function eachModuleGetsALocatorOfItsOwnNamedAfterItsAlias(): void
    {
        $container = $this->loadCatalog();

        self::assertTrue($container->hasDefinition(self::LOCATOR));
        $locator = $container->getDefinition(self::LOCATOR);
        self::assertSame(ServiceLocator::class, $locator->getClass());
        self::assertFalse($locator->isPublic());
        self::assertTrue($locator->isAutoconfigured());
        self::assertTrue($locator->isAutowired());
    }

    #[Test]
    public function theLocatorIsTaggedOnceForEveryEntityItServes(): void
    {
        $locator = $this->loadCatalog()->getDefinition(self::LOCATOR);

        self::assertSame(
            [['entity' => ProductInterface::class], ['entity' => CategoryInterface::class]],
            $locator->getTag('coolms.entity_factory'),
        );
    }

    #[Test]
    public function everyEntityGetsAnEntityFactoryBuiltForThatClass(): void
    {
        $factories = $this->loadCatalog()->getDefinition(self::LOCATOR)->getArgument('$factories');

        self::assertIsArray($factories);
        self::assertSame(CatalogExtension::ENTITIES, array_keys($factories));

        foreach ($factories as $entityClass => $factory) {
            self::assertInstanceOf(Definition::class, $factory);
            self::assertSame(EntityFactory::class, $factory->getClass());
            self::assertEquals(
                [
                    $entityClass,
                    new Reference('serializer'),
                    new Reference('serializer'), // the serializer is also the denormalizer
                    new Reference('parameter_bag'),
                ],
                $factory->getArguments(),
            );
            self::assertFalse($factory->isAutowired());
            self::assertFalse($factory->isAutoconfigured());
            self::assertFalse($factory->isPublic());
        }
    }

    #[Test]
    public function theFactoriesAreServedByEntityClassOnceTheContainerIsCompiled(): void
    {
        $container = new ContainerBuilder();

        // What a full-stack application provides.
        $container->registerForAutoconfiguration(ServiceLocator::class)->addTag('container.service_locator');
        $container->register('serializer', Serializer::class);
        $container->register('parameter_bag', ParameterBag::class);

        // The collecting side, as this bundle's extension registers it.
        $container->register('coolms.entity_factory_locator', ServiceLocator::class)
            ->addArgument(new TaggedIteratorArgument('coolms.entity_factory', 'entity'))
            ->addTag('container.service_locator');
        $container->register(EntityFactoryFactoryInterface::class, EntityFactoryFactory::class)
            ->setArgument('$locator', new Reference('coolms.entity_factory_locator'))
            ->setPublic(true);

        new CatalogExtension()->load([], $container);
        $container->compile();

        $factories = $container->get(EntityFactoryFactoryInterface::class);
        self::assertInstanceOf(EntityFactoryFactoryInterface::class, $factories);

        foreach (CatalogExtension::ENTITIES as $entityClass) {
            self::assertTrue($factories->has($entityClass));
            $factory = $factories->get($entityClass);
            self::assertInstanceOf(EntityFactory::class, $factory);
            self::assertSame($entityClass, $factory->objectClass);
        }

        self::assertFalse($factories->has(self::class));
    }

    private function loadCatalog(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        new CatalogExtension()->load([], $container);

        return $container;
    }
}
