<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use CoolMS\Core\Bundle\CoreBundle;
use CoolMS\Entity\Bundle\EntityBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function dirname;

/**
 * An application that installs this bundle and registers nothing of its own: the bundles the package requires, their
 * configuration, and NO service registration of the entity packages' classes.
 *
 * Every alias under the CoolMS namespace is made public before unused services are removed, so an alias that points
 * at a service nobody registers fails the compile instead of vanishing because nothing happened to use it. Until
 * 2026-10-16 four of this bundle's did: the host application covered them with its own scans of the entity packages.
 */
final class BundleAloneKernel extends Kernel
{
    use MicroKernelTrait;

    public const string ALIASES = 'test.coolms_aliases';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new ApiPlatformBundle();
        yield new CoreBundle();
        yield new EntityBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/coolms-entity-bundle-kernel/' . md5($this->getProjectDir()) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/coolms-entity-bundle-kernel/' . md5($this->getProjectDir()) . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class(self::ALIASES) implements CompilerPassInterface {
            public function __construct(private readonly string $parameter)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $ids = [];
                foreach ($container->getAliases() as $id => $alias) {
                    if (str_starts_with($id, 'CoolMS\\')) {
                        $alias->setPublic(true);
                        $ids[] = $id;
                    }
                }
                sort($ids);
                $container->setParameter($this->parameter, $ids);
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'not-a-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'serializer' => ['enabled' => true],
            'validation' => ['enabled' => true],
            'property_info' => ['enabled' => true],
            'translator' => ['enabled' => true, 'fallbacks' => ['en']],
            // coolms/core-bundle routes domain events to a transport of this name;
            // defining it is the installing application's part.
            'messenger' => ['transports' => ['sync' => 'sync://']],
        ]);
        $container->extension('security', [
            'providers' => ['none' => ['memory' => ['users' => []]]],
            'firewalls' => ['main' => ['stateless' => true]],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'central',
                'connections' => ['central' => ['url' => 'sqlite:///:memory:']],
            ],
            'orm' => [
                'default_entity_manager' => 'central',
                'entity_managers' => ['central' => ['connection' => 'central']],
            ],
        ]);

        $container->extension('api_platform', [
            'title' => 'entity-bundle alone',
            'mapping' => ['paths' => []],
        ]);
    }
}
