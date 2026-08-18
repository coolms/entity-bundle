<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection;

use CoolMS\EntityBundle\Factory\EntityFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Registers a module's per-entity factories with the Entity module.
 *
 * Used by module Extensions that ship entity factories. It lives here rather
 * than on Core's AbstractExtension because it names EntityFactory, and Core
 * must not depend on this module -- a module that wants factories opts in by
 * importing the trait, which is the dependency it already has.
 *
 * Requires getAlias() -- every Extension has it.
 */
trait EntityFactoryRegistrationTrait
{
    abstract public function getAlias(): string;

    /**
     * @param list<class-string> $entityClasses
     */
    protected function registerEntityFactory(ContainerBuilder $container, array $entityClasses): void
    {
        // Register the Abstract Factory that maps entity interface FQCN -- EntityFactory service.
        // A ServiceLocator is used so services are only instantiated on demand, and new entity
        // factories can be added per-module without touching this definition.
        // Each entry is an anonymous inline Definition for EntityFactory configured with the
        // correct $objectClass -- the only thing that differs between per-entity factories.
        $definition = $container->register("coolms.{$this->getAlias()}.entity_factory_locator", ServiceLocator::class);
        $factories = [];
        foreach ($entityClasses as $entityClass) {
            $factories[$entityClass] = $this->makeEntityFactory($entityClass);
            $definition->addTag('coolms.entity_factory', ['entity' => $entityClass]);
        }
        $definition->setArgument('$factories', $factories)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(false);
    }

    private function makeEntityFactory(string $entityClass): Definition
    {
        return new Definition(EntityFactory::class)
            ->setArguments([
                $entityClass,
                new Reference('serializer'),
                new Reference('serializer'),
                new Reference('parameter_bag'),
            ])->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false);
    }
}
