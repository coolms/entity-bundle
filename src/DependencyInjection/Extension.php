<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection;

use CoolMS\Entity\Contract\EntityTypeSchemaContributorInterface;
use CoolMS\Entity\Contract\ExtrasNormalizationExclusionInterface;
use CoolMS\Entity\Doctrine\Cache\ExtrasSchemaCacheInvalidator;
use CoolMS\Entity\Doctrine\ClassMetaEntityAliasRegistry;
use CoolMS\Entity\Doctrine\Listener\ExtrasValidationListener;
use CoolMS\Entity\Doctrine\Mapping\ExtrasFieldMappingDriver;
use CoolMS\Entity\Doctrine\Mapping\TraitMappingDriver;
use CoolMS\Entity\Doctrine\Repository\DoctrineEntitySchemaProvider;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use CoolMS\Entity\Field\EntityFieldDescriptorInterface;
use CoolMS\Entity\Registry\EntityAliasRegistry;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;
use CoolMS\Entity\Repository\EntitySchemaProviderInterface;
use CoolMS\Entity\Resolver\EntityAliasResolverInterface;
use CoolMS\Entity\Resolver\EntityResolverInterface;
use CoolMS\Entity\Service\EntitySchemaLookup;
use CoolMS\Entity\VirtualField\VirtualFieldRegistry;
use CoolMS\Entity\VirtualField\VirtualFieldRegistryInterface;
use CoolMS\EntityBundle\ApiPlatform\Metadata\ExtrasPropertyMetadataFactory;
use CoolMS\EntityBundle\ApiPlatform\Metadata\ExtrasPropertyNameCollectionFactory;
use CoolMS\EntityBundle\Factory\EntityFactoryFactory;
use CoolMS\EntityModule\Field\ReflectionEntityFieldDescriptor;
use CoolMS\EntityModule\Resolver\RepositoryEntityAliasResolver;
use CoolMS\EntityModule\Serializer\ExtrasFlatteningNormalizer;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * DI extension for the entity packages.
 *
 * Hosts wiring for the broad ExtrasProvider contract:
 *  - `EntityAliasRegistry` (FQCN -> alias map, populated by per-module
 *    compiler passes via `addMethodCall('register', ...)`).
 *  - `EntitySchemaLookup` (alias -> field schema).
 *  - Doctrine listeners, mapping driver, normalizer, and API Platform
 *    property metadata factories that all dispatch on
 *    `ExtrasProviderInterface`.
 *
 * Alias bindings for `EntityAliasRegistryInterface`,
 * `EntityFieldDescriptorInterface`,
 * `EntityAliasResolverInterface`, and `VirtualFieldRegistryInterface`
 * carry over from the prior Phase 2 / Phase X-2.5 wiring.
 */
final class Extension extends BaseExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Author-facing entity-alias registry
        // (ClassMeta-driven). Bundles that ship dynamic alias sources
        // can override the alias to point at a composite implementation.
        $container->setAlias(EntityAliasRegistryInterface::class, ClassMetaEntityAliasRegistry::class);

        // Phase X-2.5 -- Reflection-based entity field descriptor.
        $container->setAlias(EntityFieldDescriptorInterface::class, ReflectionEntityFieldDescriptor::class);

        // entity:find / entity:findAll widget adapter.
        $container->setAlias(EntityAliasResolverInterface::class, RepositoryEntityAliasResolver::class);

        // Phase X-2.5b -- virtual-field registry.
        $container->setAlias(VirtualFieldRegistryInterface::class, VirtualFieldRegistry::class);

        // Entity resolvers are collected into EntityResolverChain by this tag.
        // Declared here rather than by #[AutoconfigureTag] on the interface so
        // coolms/entity carries no dependency on the container it runs in --
        // a package must not spell its own wiring (see the Configuration +
        // compiler-pass convention). The chain's iterator argument is
        // re-asserted post-scan by ExtrasInfrastructurePass.
        $container->registerForAutoconfiguration(EntityResolverInterface::class)
            ->addTag('coolms.entity_resolver');

        $container->setParameter(
            'coolms.entity.field_config_dir',
            '%kernel.project_dir%/config/dynamic_entity',
        );

        $config = $this->processConfiguration(new Configuration(), $configs);

        // Opt-in extras enforcement (see Configuration). Held as parameters so
        // ExtrasInfrastructurePass can re-assert them after the `App\` glob
        // replaces the listener definition -- same reason as the scalar args on
        // EntitySchemaLookup below.
        $container->setParameter(
            'coolms.entity.extras_validation.aliases',
            $config['extras_validation']['aliases'],
        );
        $container->setParameter(
            'coolms.entity.extras_validation.exclude',
            $config['extras_validation']['exclude'],
        );

        $this->registerEntityFactories($container);
        $this->registerExtrasInfrastructure($container);
    }

    public function getAlias(): string
    {
        return 'entity';
    }

    /**
     * Register the Abstract Factory that maps entity interface FQCN -- EntityFactory service.
     * A ServiceLocator is used so services are only instantiated on demand, and new entity
     * factories can be added per-module without touching this definition.
     * Each entry is an anonymous inline Definition for EntityFactory configured with the
     * correct $objectClass -- the only thing that differs between per-entity factories.
     *
     * Moved here from Core's Extension: both classes are this module's, and Core has no
     * business naming them.
     */
    private function registerEntityFactories(ContainerBuilder $container): void
    {
        $container->register(EntityFactoryFactoryInterface::class, EntityFactoryFactory::class)
            ->setDecoratedService('coolms.entity_factory_locator')
            ->setArgument('$locator', new Reference('.inner'));

        // The 'entity' index attribute is carried by every 'coolms.entity_factory' tag
        // (see EntityFactoryRegistrationTrait::registerEntityFactory), so no default index
        // method is needed -- and #[AsTaggedItem] is not an option here: the tagged services
        // are all instances of the vendor ServiceLocator class, and each definition carries
        // one tag per entity class, which a single class-level attribute cannot express.
        $container->register('coolms.entity_factory_locator', ServiceLocator::class)
            ->addArgument(new TaggedIteratorArgument('coolms.entity_factory', 'entity'))
            ->addTag('container.service_locator');
    }

    private function registerExtrasInfrastructure(ContainerBuilder $container): void
    {
        // EntityAliasRegistry: FQCN -> alias map. Each module populates it at
        // compile time via its own RegistrationPass, which
        // addMethodCall('register', [Class::class, 'alias']) after all
        // Extensions have been merged into the shared container.
        $container->register(EntityAliasRegistry::class)
            ->setArguments([[]])
            ->setPublic(false);

        // Entity schema introspection -- ORM-agnostic interface wired to Doctrine implementation.
        $container->register(DoctrineEntitySchemaProvider::class)
            ->setArgument('$aliases', new Reference(EntityAliasRegistry::class))
            ->setAutowired(true);
        $container->setAlias(EntitySchemaProviderInterface::class, DoctrineEntitySchemaProvider::class)
            ->setPublic(false);

        // ExtrasFieldMappingDriver decorates the central metadata driver to
        // surface generated v_{name} virtual columns for non-Extras aliased
        // entities. Uses DBAL Connection (not ORM repo) to avoid circular
        // dependency with ORM (which needs metadata to boot). NamingStrategy
        // transforms the field name to its physical column form (snake_case
        // under the project's UnderscoreNamingStrategy).
        $container->register(ExtrasFieldMappingDriver::class)
            ->setDecoratedService('doctrine.orm.central_metadata_driver', null, 0)
            ->setArgument('$delegate', new Reference('.inner'))
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class))
            ->setArgument('$namingStrategy', new Reference('doctrine.orm.naming_strategy.underscore'))
            ->setAutowired(true) // autowires Connection
            ->setAutoconfigured(false)
            ->setPublic(false);

        // Translates the Domain's persistence-neutral mapping attributes on the
        // reusable traits into Doctrine metadata, so Domain/Traits carries no
        // Doctrine import. See TraitMappingDriver.
        //
        // Two decorators now wrap the same metadata driver, so both declare an
        // explicit priority rather than relying on registration order. They are
        // independent -- one adds trait columns, the other adds generated
        // v_{name} columns -- but a tie decided by definition order is the kind
        // of thing that changes silently when a file moves.
        $container->register(TraitMappingDriver::class)
            ->setDecoratedService('doctrine.orm.central_metadata_driver', null, 10)
            ->setArgument('$delegate', new Reference('.inner'))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false);

        // Scalar args ($configDir, $modulesDir) are re-asserted post-scan by
        // ExtrasInfrastructurePass -- the App\: prototype scan replaces this
        // whole definition, so setting them here alone is not enough.
        // $aliasRegistry is the canonical shared instance for appliesTo filtering.
        $container->register(EntitySchemaLookup::class)
            ->setArgument('$configDir', '%coolms.entity.field_config_dir%')
            ->setArgument('$modulesDir', '%kernel.project_dir%/config/modules')
            ->setArgument(
                '$typeContributor',
                new Reference(EntityTypeSchemaContributorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            )
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class))
            ->setAutowired(true);

        // Validates ExtrasProviderInterface entity extras against the schema on
        // every save, for the aliases opted in via `entity.extras_validation`.
        // The tags here are NOT sufficient on their own -- the `App\` glob
        // replaces this definition and drops them, which is why
        // ExtrasInfrastructurePass re-adds them.
        $container->register(ExtrasValidationListener::class)
            ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class))
            ->setArgument('$aliases', '%coolms.entity.extras_validation.aliases%')
            ->setArgument('$exclude', '%coolms.entity.extras_validation.exclude%')
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->addTag('doctrine.event_listener', ['event' => 'prePersist'])
            ->addTag('doctrine.event_listener', ['event' => 'preUpdate']);

        $container->register(ExtrasPropertyMetadataFactory::class)
            ->setDecoratedService('api_platform.metadata.property.metadata_factory')
            ->setArgument('$delegate', new Reference('.inner'))
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class));

        $container->register(ExtrasPropertyNameCollectionFactory::class)
            ->setDecoratedService('api_platform.metadata.property.name_collection_factory')
            ->setArgument('$delegate', new Reference('.inner'))
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class));

        // $cache reference is fixed up by ExtrasInfrastructurePass (cache.app.taggable).
        $container->register(ExtrasSchemaCacheInvalidator::class)
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class))
            ->setAutowired(true);

        // Modules that normalize a type themselves claim it here so the
        // flattening normalizer stands aside even when their higher-priority
        // normalizer is not registered.
        $container->registerForAutoconfiguration(ExtrasNormalizationExclusionInterface::class)
            ->addTag('coolms.extras_normalization_exclusion');

        // Must run before ObjectNormalizer (priority 10 > 0) so aliased entities are handled first.
        $container->register(ExtrasFlatteningNormalizer::class)
            ->setArgument('$objectNormalizer', new Reference('serializer.normalizer.object'))
            ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
            ->setArgument('$aliasRegistry', new Reference(EntityAliasRegistry::class))
            ->setArgument('$exclusions', new TaggedIteratorArgument('coolms.extras_normalization_exclusion'))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->addTag('serializer.normalizer', ['priority' => 10]);
    }
}
