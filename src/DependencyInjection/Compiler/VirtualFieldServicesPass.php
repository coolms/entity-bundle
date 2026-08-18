<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\DependencyInjection\Compiler;

use CoolMS\Entity\VirtualField\VirtualFieldProviderInterface;
use CoolMS\Entity\VirtualField\VirtualFieldRegistry;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Phase X-2.5b -- (re-)tags every `VirtualFieldProviderInterface`
 * implementation and binds the resulting iterable to
 * `VirtualFieldRegistry`'s constructor.
 *
 * Runs as a compiler pass (not from the bundle extension's `load()`
 * or via `#[AutoconfigureTag]`) because the `App\:` prototype scan
 * in `config/services.yaml` processes AFTER bundle extensions and
 * replaces autoconfigured definitions with fresh autowire-true
 * ones, losing the tag. Re-tagging in the default
 * BEFORE_OPTIMIZATION phase -- after the prototype scan, before the
 * autowire-validation pass -- ensures the tag survives.
 *
 * The same pattern several application modules use for services the prototype
 * scan would otherwise re-register, extended here to cover both tagging and
 * iterable binding.
 */
final class VirtualFieldServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if (null === $class || !class_exists($class)) {
                continue;
            }
            if (is_subclass_of($class, VirtualFieldProviderInterface::class)
                && !$definition->hasTag(VirtualFieldProviderInterface::TAG_NAME)
            ) {
                $definition->addTag(VirtualFieldProviderInterface::TAG_NAME);
            }
        }

        if ($container->hasDefinition(VirtualFieldRegistry::class)) {
            $container->getDefinition(VirtualFieldRegistry::class)
                ->setArgument('$providers', new TaggedIteratorArgument(VirtualFieldProviderInterface::TAG_NAME));
        }
    }
}
