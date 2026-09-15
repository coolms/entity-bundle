<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Dtmpl;

use CoolMS\Dtmpl\Validation\AliasResolverInterface;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;

/**
 * Answers the template validator's `@alias` question from the entity
 * alias registry. The engine declares the port; this bundle owns the
 * dictionary, so the answer lives here.
 *
 * `resolve()` admits every registered alias, singular and collection
 * alike, because a template may read either. `knownAliases()` lists
 * the singular ones, which is what an author is told to choose from
 * when a typo is refused.
 */
final readonly class TemplateAliasResolver implements AliasResolverInterface
{
    public function __construct(private EntityAliasRegistryInterface $registry)
    {
    }

    public function resolve(string $alias): ?string
    {
        return $this->registry->resolve($alias);
    }

    public function knownAliases(): array
    {
        return array_keys($this->registry->all());
    }
}
