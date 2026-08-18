<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection\Compiler;

use CoolMS\Entity\Doctrine\Tree\DoctrineNestedSetOperator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Resolves interface-typed $entityClass arguments in DoctrineNestedSetOperator definitions
 * using the coolms.mapping.resolve_target_entities map accumulated by all bundle Extensions.
 *
 * This mirrors how Doctrine's resolve_target_entities works at the DQL/mapping level, but
 * applies at the DI-compile level so ManagerRegistry::getManagerForClass() receives a
 * concrete class (it cannot resolve interfaces itself).
 *
 * Bundles contribute to the map by calling AbstractExtension::setResolveTargetEntities().
 * This pass runs after all Extensions have loaded, so the accumulated map is complete.
 *
 * Lives in Entity, not Core: the definitions it rewrites are Entity's own Doctrine tree
 * operator. Core only owns the parameter, which is a plain string map and costs Core no
 * dependency on this module.
 */
final readonly class ResolveTargetEntityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('coolms.mapping.resolve_target_entities')) {
            return;
        }

        /** @var array<class-string, class-string> $map */
        $map = $container->getParameter('coolms.mapping.resolve_target_entities');

        if (empty($map)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if (DoctrineNestedSetOperator::class !== $definition->getClass()) {
                continue;
            }

            $entityClass = $definition->getArgument('$entityClass');

            if (!is_string($entityClass)) {
                continue;
            }

            if (isset($map[$entityClass])) {
                $definition->setArgument('$entityClass', $map[$entityClass]);
            }
        }
    }
}
