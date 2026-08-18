<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle;

use CoolMS\CoreBundle\AbstractCoolmsBundle;
use CoolMS\Entity\Field\FilterFieldContributorInterface;
use CoolMS\EntityBundle\DependencyInjection\Compiler\ExtrasInfrastructurePass;
use CoolMS\EntityBundle\DependencyInjection\Compiler\FilterFieldContributorPass;
use CoolMS\EntityBundle\DependencyInjection\Compiler\ResolveTargetEntityPass;
use CoolMS\EntityBundle\DependencyInjection\Compiler\VirtualFieldServicesPass;
use CoolMS\EntityBundle\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Root bundle for the entity packages.
 *
 * Peer to `CoreBundle` at the L0 layer; hosts entity concepts
 * extracted out of Core:
 *
 *  - `Domain/Attribute/` — `ClassMeta`, `FieldMeta` (entity metadata)
 *  - `Application/` -- entity resolver chain, alias registry, and
 *    the canonical RQL-based search contract surface
 *    (`RqlRepositoryInterface` from packages/rql, `FreeTextProjector`
 *    for picker free-text projection)
 *  - `Infrastructure/` — reflection-based field extractor + tagged
 *    repository registry
 *  - `Infrastructure/Doctrine/` — `ClassMetaEntityAliasRegistry`
 *
 * The split keeps Core focused on framework primitives (ApiManifest,
 * config loaders, factory plumbing, hierarchy generators) while
 * Entity owns the entity-shape vocabulary higher modules consume.
 */
class EntityBundle extends AbstractCoolmsBundle
{
    public const string COMPONENT_NAME = 'coolms_entity';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->registerForAutoconfiguration(FilterFieldContributorInterface::class)
            ->addTag('coolms.entity.filter_field_contributor');
        // The predicate half moved with the translator: RqlDoctrineBundle
        // autoconfigures FilterPredicateContributorInterface and injects the
        // tagged services into the visitor itself.
        $container->addCompilerPass(new VirtualFieldServicesPass());
        // Priority 50 is load-bearing, not decoration. The pass re-adds the
        // `doctrine.event_listener` tags that the `App\` glob strips off
        // ExtrasValidationListener, and DoctrineBundle's
        // RegisterEventListenersAndSubscribersPass -- which COLLECTS those tags
        // -- also runs in BEFORE_OPTIMIZATION at priority 0. At equal priority
        // the winner is bundle registration order, and DoctrineBundle is
        // registered first, so the tags would be re-added after the only pass
        // that reads them and the listener would never reach the EventManager.
        // 50 sits below Symfony's own 100-block (ResolveClassPass,
        // ResolveInstanceofConditionalsPass, ...) and above Doctrine's 0.
        $container->addCompilerPass(new ExtrasInfrastructurePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 50);
        // Rewrites interface-typed $entityClass args on DoctrineNestedSetOperator
        // definitions. Moved here from CoreBundle: the target class is Entity's.
        $container->addCompilerPass(new ResolveTargetEntityPass());
        // Feeds the tagged contributors into EntityFieldsProvider. A pass,
        // not Extension wiring: the `App\` glob in config/services.yaml
        // re-registers the provider after bundle extensions and silently
        // discards their setArgument() calls.
        $container->addCompilerPass(new FilterFieldContributorPass());
    }

    public function getContainerExtension(): Extension
    {
        return new Extension();
    }
}
