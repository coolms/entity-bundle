<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Kernel;

use CoolMS\Entity\Application\Field\ReflectionEntityFieldDescriptor;
use CoolMS\Entity\Application\Resolver\RepositoryEntityAliasResolver;
use CoolMS\Entity\Doctrine\ClassMetaEntityAliasRegistry;
use CoolMS\Entity\Field\EntityFieldDescriptorInterface;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;
use CoolMS\Entity\Resolver\EntityAliasResolverInterface;
use CoolMS\Entity\VirtualField\VirtualFieldRegistry;
use CoolMS\Entity\VirtualField\VirtualFieldRegistryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function count;
use function dirname;
use function restore_exception_handler;
use function set_exception_handler;

/**
 * The bundle alone -- installed with nothing of the application's own -- compiles, and every alias and interface
 * binding it declares resolves to a service that is registered (see BundleAloneKernel: each one is made public, so a
 * dangling one fails the compile).
 */
final class BundleAloneResolvesItsAliasesTest extends TestCase
{
    private BundleAloneKernel $kernel;

    /** PHP's exception handler before the boot: the framework installs one, and the test leaves none behind. */
    private mixed $handlerBefore = null;

    #[Test]
    public function everyAliasItDeclaresResolves(): void
    {
        $container = $this->kernel->getContainer();
        $aliases = $container->getParameter(BundleAloneKernel::ALIASES);
        self::assertIsArray($aliases);
        self::assertNotEmpty($aliases, 'the kernel recorded the aliases it made public');

        $unresolved = array_values(array_filter($aliases, static fn (string $id): bool => !$container->has($id)));

        self::assertSame([], $unresolved, count($aliases) . ' aliases declared');
    }

    #[Test]
    public function thePortsThatPointedAtUnregisteredClassesResolveToThem(): void
    {
        $container = $this->kernel->getContainer();

        foreach ([
            EntityAliasRegistryInterface::class => ClassMetaEntityAliasRegistry::class,
            EntityFieldDescriptorInterface::class => ReflectionEntityFieldDescriptor::class,
            EntityAliasResolverInterface::class => RepositoryEntityAliasResolver::class,
            VirtualFieldRegistryInterface::class => VirtualFieldRegistry::class,
        ] as $port => $class) {
            self::assertInstanceOf($class, $container->get($port), $port);
        }
    }

    protected function setUp(): void
    {
        new Filesystem()->remove(dirname(new BundleAloneKernel('test', false)->getCacheDir()));
        $this->handlerBefore = set_exception_handler(null);
        restore_exception_handler();
        $this->kernel = new BundleAloneKernel('test', false);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        for ($guard = 0; $guard < 10; ++$guard) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->handlerBefore) {
                break;
            }
            restore_exception_handler();
        }
    }
}
