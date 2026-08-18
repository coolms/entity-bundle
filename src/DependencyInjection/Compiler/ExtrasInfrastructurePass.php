<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection\Compiler;

use CoolMS\Entity\Contract\EntityTypeSchemaContributorInterface;
use CoolMS\Entity\Doctrine\Cache\ExtrasSchemaCacheInvalidator;
use CoolMS\Entity\Doctrine\Listener\ExtrasValidationListener;
use CoolMS\Entity\Doctrine\Mapping\ExtrasFieldMappingDriver;
use CoolMS\Entity\Doctrine\Mapping\TraitMappingDriver;
use CoolMS\Entity\Registry\EntityAliasRegistry;
use CoolMS\Entity\Resolver\EntityResolverChain;
use CoolMS\Entity\Service\EntitySchemaLookup;
use CoolMS\EntityBundle\ApiPlatform\Metadata\ExtrasPropertyMetadataFactory;
use CoolMS\EntityBundle\ApiPlatform\Metadata\ExtrasPropertyNameCollectionFactory;
use CoolMS\EntityModule\Serializer\ExtrasFlatteningNormalizer;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Re-asserts wiring for ExtrasProvider infrastructure after the `App\:`
 * services.yaml resource scan, which would otherwise overwrite the
 * Extension's definitions and drop:
 *
 *   - `setDecoratedService()` markers on the decorators.
 *   - Scalar arguments on `EntitySchemaLookup` (`$configDir`, `$modulesDir`).
 *   - `doctrine.event_listener` tags on `ExtrasValidationListener` -- a dropped
 *     tag is silent, so the service still exists and simply never fires.
 *   - The `EntityAliasRegistry` reference on consumer services (the registry
 *     is the canonical shared instance; auto-scan can re-register consumers
 *     with autowired but non-equal `EntityAliasRegistry` references).
 *
 * A runtime-types module does the same for the narrow services that stay in
 * its own infrastructure.
 */
final class ExtrasInfrastructurePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Optional: only present when a runtime-type module is
        // installed. Absent means no schema inheritance -- every other extras
        // capability is unaffected.
        $typeContributorRef = $container->has(EntityTypeSchemaContributorInterface::class)
            ? new Reference(EntityTypeSchemaContributorInterface::class)
            : null;

        $aliasRegistryRef = new Reference(EntityAliasRegistry::class);

        // The resolver chain's iterable used to be filled by #[AutowireIterator]
        // on its constructor. The attribute is gone -- a package should not name
        // the container -- so the tagged iterator is asserted here, after the
        // service scan that would otherwise re-register the chain autowired and
        // hand it an EMPTY iterable. Empty is the dangerous outcome: every
        // resolve() would return null and every search() an empty list, with
        // nothing failing to say so.
        if ($container->has(EntityResolverChain::class)) {
            $container->findDefinition(EntityResolverChain::class)
                ->setArgument(
                    '$resolvers',
                    new TaggedIteratorArgument('coolms.entity_resolver', null, null, false, 'priority'),
                );
        }

        if ($container->has(EntitySchemaLookup::class)) {
            $def = $container->findDefinition(EntitySchemaLookup::class);
            $def->setArgument('$configDir', '%coolms.entity.field_config_dir%');
            $def->setArgument('$modulesDir', '%kernel.project_dir%/config/modules');
            if (null !== $typeContributorRef) {
                $def->setArgument('$typeContributor', $typeContributorRef);
            }
            $def->setArgument('$aliasRegistry', $aliasRegistryRef);
        }

        if ($container->has(ExtrasFieldMappingDriver::class)) {
            $container->findDefinition(ExtrasFieldMappingDriver::class)
                ->setDecoratedService('doctrine.orm.central_metadata_driver', null, 0)
                ->setArgument('$delegate', new Reference('.inner'))
                ->setArgument('$aliasRegistry', $aliasRegistryRef)
                ->setArgument('$namingStrategy', new Reference('doctrine.orm.naming_strategy.underscore'));
        }

        // Re-asserted for the same reason as the driver above: the `App\` glob
        // loads after bundle extensions and replaces the definition, taking the
        // decoration with it. Without this the trait attributes are read by
        // nothing and every trait column silently disappears from the mapping.
        if ($container->has(TraitMappingDriver::class)) {
            $container->findDefinition(TraitMappingDriver::class)
                ->setDecoratedService('doctrine.orm.central_metadata_driver', null, 10)
                ->setArgument('$delegate', new Reference('.inner'))
                ->setAutowired(false);
        }

        if ($container->has(ExtrasSchemaCacheInvalidator::class)) {
            $container->findDefinition(ExtrasSchemaCacheInvalidator::class)
                ->setArgument('$cache', new Reference('cache.app.taggable'))
                ->setArgument('$aliasRegistry', $aliasRegistryRef);
        }

        if ($container->has(ExtrasFlatteningNormalizer::class)) {
            $container->findDefinition(ExtrasFlatteningNormalizer::class)
                ->setArgument('$objectNormalizer', new Reference('serializer.normalizer.object'))
                ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
                ->setArgument('$aliasRegistry', $aliasRegistryRef)
                ->setArgument('$exclusions', new TaggedIteratorArgument('coolms.extras_normalization_exclusion'));
        }

        // The TAGS are the load-bearing part here. The Extension adds them, but
        // the `App\` glob replaces the whole definition and they go with it --
        // leaving a service that is wired, injectable, and never called by
        // Doctrine. (The sibling ExtrasSchemaCacheInvalidatorListener survives
        // the same scan only because it carries #[AsDoctrineListener], which
        // autoconfiguration re-applies.) Without this block the listener is
        // dead code and every `required` extras field is silently unenforced.
        if ($container->has(ExtrasValidationListener::class)) {
            $container->findDefinition(ExtrasValidationListener::class)
                ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
                ->setArgument('$aliasRegistry', $aliasRegistryRef)
                ->setArgument('$aliases', '%coolms.entity.extras_validation.aliases%')
                ->setArgument('$exclude', '%coolms.entity.extras_validation.exclude%')
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->clearTag('doctrine.event_listener')
                ->addTag('doctrine.event_listener', ['event' => 'prePersist'])
                ->addTag('doctrine.event_listener', ['event' => 'preUpdate']);
        }

        if ($container->has(ExtrasPropertyMetadataFactory::class)) {
            $container->findDefinition(ExtrasPropertyMetadataFactory::class)
                ->setDecoratedService('api_platform.metadata.property.metadata_factory')
                ->setArgument('$delegate', new Reference('.inner'))
                ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
                ->setArgument('$aliasRegistry', $aliasRegistryRef);
        }

        if ($container->has(ExtrasPropertyNameCollectionFactory::class)) {
            $container->findDefinition(ExtrasPropertyNameCollectionFactory::class)
                ->setDecoratedService('api_platform.metadata.property.name_collection_factory')
                ->setArgument('$delegate', new Reference('.inner'))
                ->setArgument('$schemaLookup', new Reference(EntitySchemaLookup::class))
                ->setArgument('$aliasRegistry', $aliasRegistryRef);
        }
    }
}
