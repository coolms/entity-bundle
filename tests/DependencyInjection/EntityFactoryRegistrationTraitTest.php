<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Entity\Bundle\DependencyInjection\Extension;
use CoolMS\Entity\Bundle\Factory\EntityFactory;
use CoolMS\Entity\Bundle\Tests\Fixture\CatalogExtension;
use CoolMS\Entity\Bundle\Tests\Fixture\CategoryInterface;
use CoolMS\Entity\Bundle\Tests\Fixture\ProductInterface;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
 * The module's locator tags itself `container.service_locator`, the tag that
 * turns the inline definitions into the lazy factories a locator needs. A
 * full-stack application's autoconfiguration adds that tag to every
 * ServiceLocator as well, so the compiled cases run with that rule and
 * without it. Both take the collecting side from this bundle's own extension.
 * The fixture is the shape a consumer writes.
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
    public function theLocatorTagsItselfAsAServiceLocator(): void
    {
        $locator = $this->loadCatalog()->getDefinition(self::LOCATOR);

        self::assertTrue($locator->hasTag('container.service_locator'));
    }

    #[Test]
    public function theFactoriesAreServedByEntityClassOnceTheContainerIsCompiled(): void
    {
        $this->assertFactoriesServed($this->compiledFactories(autoconfiguration: true));
    }

    #[Test]
    public function theFactoriesAreServedWithAutoconfigurationOff(): void
    {
        $this->assertFactoriesServed($this->compiledFactories(autoconfiguration: false));
    }

    private function compiledFactories(bool $autoconfiguration): object
    {
        $container = new ContainerBuilder();

        // What a full-stack application provides, when it autoconfigures.
        if ($autoconfiguration) {
            $container->registerForAutoconfiguration(ServiceLocator::class)->addTag('container.service_locator');
        }
        $container->register('serializer', Serializer::class);
        $container->register('parameter_bag', ParameterBag::class);

        // The collecting side, taken from this bundle's own extension rather than
        // restated: its tag, its `entity` index and its decoration are what is
        // under test. Only these two definitions are taken -- the rest of the
        // extension decorates services a unit container does not have.
        $bundle = new ContainerBuilder();
        new Extension()->load([], $bundle);
        foreach (['coolms.entity_factory_locator', EntityFactoryFactoryInterface::class] as $id) {
            $container->setDefinition($id, $bundle->getDefinition($id));
        }
        $container->setAlias('test.entity_factories', EntityFactoryFactoryInterface::class)->setPublic(true);

        new CatalogExtension()->load([], $container);
        if (!$autoconfiguration) {
            $container->getDefinition(self::LOCATOR)->setAutoconfigured(false);
        }
        $container->compile();

        return $container->get('test.entity_factories');
    }

    private function assertFactoriesServed(object $factories): void
    {
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
