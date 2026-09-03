<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Tests\DependencyInjection\Compiler;

use CoolMS\EntityBundle\DependencyInjection\Compiler\VirtualFieldServicesPass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function spl_autoload_register;
use function spl_autoload_unregister;

/**
 * The pass walks EVERY definition in the container, and a container holds
 * classes from every installed bundle -- not only ours.
 *
 * Some of those classes extend an optional dependency the application never
 * installed. `doctrine/doctrine-bundle` ships a Twig extension and declares
 * twig nowhere; `symfony/translation` ships AST visitors that want
 * `nikic/php-parser`. Loading one of those is a fatal at compile time, in an
 * application that never asked for either library.
 *
 * So a class that will not load is one this bundle does not own, and that is
 * never an error here. The test plants exactly that: an autoloader that
 * throws for one class name, which is what PHP does when a parent class is
 * missing.
 */
final class VirtualFieldServicesPassTest extends TestCase
{
    #[Test]
    public function aClassThatCannotBeLoadedIsSkippedRatherThanFatal(): void
    {
        $unloadable = 'CoolMS\\EntityBundle\\Tests\\Fixture\\NeedsAnAbsentParent';

        // What PHP does when a class file loads but its parent does not.
        $autoloader = static function (string $class) use ($unloadable): void {
            if ($class === $unloadable) {
                throw new \Error('Class "Absent" not found');
            }
        };
        spl_autoload_register($autoloader, true, true);

        try {
            $container = new ContainerBuilder();
            $container->setDefinition('not.ours', new Definition($unloadable));

            (new VirtualFieldServicesPass())->process($container);

            self::assertTrue($container->hasDefinition('not.ours'), 'the definition survives untouched');
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }
}
