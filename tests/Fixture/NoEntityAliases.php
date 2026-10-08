<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Entity\Resolver\EntityAliasResolverInterface;

/**
 * The alias resolver the two entity widgets are built with, answering nothing:
 * the switch is about whether they are registered, not about what they read.
 */
final class NoEntityAliases implements EntityAliasResolverInterface
{
    public function find(string $alias, ?string $rqlFilter = null): ?object
    {
        return null;
    }

    public function findAll(string $alias, ?string $rqlFilter = null): array
    {
        return [];
    }
}
