<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Entity\Bundle\DependencyInjection\EntityFactoryRegistrationTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * The DI extension of a module that ships entity factories, in the shape a
 * consumer writes one: it uses the trait and passes the entity contracts it
 * owns from its load().
 */
final class CatalogExtension extends Extension
{
    use EntityFactoryRegistrationTrait;

    public const array ENTITIES = [
        ProductInterface::class,
        CategoryInterface::class,
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->registerEntityFactory($container, self::ENTITIES);
    }

    public function getAlias(): string
    {
        return 'catalog';
    }
}
