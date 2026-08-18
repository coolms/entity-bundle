<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection\Compiler;

use CoolMS\EntityBundle\ApiPlatform\Resource\Provider\EntityFieldsProvider;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Injects the tagged `FilterFieldContributorInterface` implementations into
 * `EntityFieldsProvider`.
 *
 * A compiler pass rather than Extension wiring for the documented reason: the
 * `App\: resource: '../src/'` glob loads AFTER bundle extensions and
 * re-registers the provider, silently dropping any `setArgument()` an
 * extension made. A pass runs once every definition exists, so the wiring
 * survives. Same trap as the tagged-iterator glob override.
 */
final class FilterFieldContributorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(EntityFieldsProvider::class)) {
            $container->findDefinition(EntityFieldsProvider::class)
                ->setArgument(
                    '$contributors',
                    new TaggedIteratorArgument('coolms.entity.filter_field_contributor'),
                );
        }

        // The predicate half moved out with the translator.
        // RqlDoctrineBundle owns it now, as a pass there for the same reason it
        // was one here: it rides the visitor, the one funnel every repository's
        // RQL passes through, so a contributing module needs no constructor
        // change in any repository it extends.
    }
}
