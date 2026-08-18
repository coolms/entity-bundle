<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the Entity module. Today covers the
 * `extras_validation` block only -- which aliases have their required
 * extras fields enforced by ExtrasValidationListener on save.
 *
 * The block is OPT-IN and defaults to enforcing nothing, on purpose.
 * Enforcement rejects writes that succeed today, and a required field
 * declared against an alias with existing rows is a data migration, not
 * a flag flip: the `vfs_node` alias alone carries two required fields
 * that no existing node satisfies. Opting an alias in is therefore a
 * deliberate act, taken once its stored rows have been backfilled.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('entity');
        $root = $tree->getRootNode();

        $root
            ->children()
            ->arrayNode('extras_validation')
            ->addDefaultsIfNotSet()
            ->info('Enforcement of `required` / `isRequired` extras fields on persist and update.')
            ->children()
            // Entity aliases to enforce. Empty (the default) enforces
            // nothing. The single entry `*` enforces every aliased
            // ExtrasProvider, which is the end state a migration works
            // toward -- paired with `exclude` for the stragglers.
            ->arrayNode('aliases')
            ->info('Aliases to enforce, or ["*"] for all. Empty enforces nothing.')
            ->scalarPrototype()->cannotBeEmpty()->end()
            ->defaultValue([])
            ->end()
            // Only meaningful alongside `*`; an alias named here is never
            // enforced. This is what keeps a "turn it on everywhere"
            // rollout from being blocked by one un-backfilled alias.
            ->arrayNode('exclude')
            ->info('Aliases never enforced, even when `aliases` is ["*"].')
            ->scalarPrototype()->cannotBeEmpty()->end()
            ->defaultValue([])
            ->end()
            ->end()
            ->end()
            ->end();

        return $tree;
    }
}
