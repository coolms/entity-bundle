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
            if (null === $class) {
                continue;
            }
            // A container holds classes from EVERY installed bundle, and some of
            // them extend an optional dependency the application never installed
            // -- doctrine-bundle ships a Twig extension and declares twig
            // nowhere. Force-loading such a class is a fatal at compile time, in
            // an application that has never mentioned Twig. A class that will not
            // load is therefore "not ours", never an error.
            //
            // Invisible in the CoolMS application, which requires
            // symfony/twig-bundle. Found from a clean checkout of a minimal
            // application that requires neither library.
            try {
                if (!class_exists($class) || !is_subclass_of($class, VirtualFieldProviderInterface::class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            if (!$definition->hasTag(VirtualFieldProviderInterface::TAG_NAME)) {
                $definition->addTag(VirtualFieldProviderInterface::TAG_NAME);
            }
        }

        if ($container->hasDefinition(VirtualFieldRegistry::class)) {
            $container->getDefinition(VirtualFieldRegistry::class)
                ->setArgument('$providers', new TaggedIteratorArgument(VirtualFieldProviderInterface::TAG_NAME));
        }
    }
}
