<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\DependencyInjection\Compiler;

use CoolMS\Entity\Bundle\Field\NoRuntimeFields;
use CoolMS\Entity\Contract\FieldMetadataSourceInterface;
use CoolMS\Entity\Contract\FieldSchemaSourceInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Binds the two field-management ports to {@see NoRuntimeFields}, each only when no package has bound it.
 *
 * A compiler pass, not a check in the extension: bundles load in order, and the Field family's extension -- which
 * binds both ports -- may load after this one. By the time a pass runs every extension has loaded, so a real binding
 * always wins and the fallback only fills a port that would otherwise leave the schema lookup and the schema provider
 * unbuildable (measured 2026-10-16: a kernel with this bundle and no Field package did not compile).
 */
final class FieldSchemaSourceFallbackPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ([FieldSchemaSourceInterface::class, FieldMetadataSourceInterface::class] as $port) {
            if ($container->hasAlias($port) || $container->hasDefinition($port)) {
                continue;
            }
            if (!$container->hasDefinition(NoRuntimeFields::class)) {
                // The class named: a pass runs after the step that fills it in from the id.
                $container->register(NoRuntimeFields::class, NoRuntimeFields::class)->setPublic(false);
            }
            $container->setAlias($port, NoRuntimeFields::class)->setPublic(false);
        }
    }
}
