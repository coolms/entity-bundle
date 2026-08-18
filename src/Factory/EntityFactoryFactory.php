<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Factory;

use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use CoolMS\Entity\Factory\EntityFactoryInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Entity Factory Factory.
 *
 * Registry of EntityFactory services keyed by entity interface FQCN.
 * Populated via ServiceLocator at container compile time -- each module's
 * Extension registers its per-entity factories and passes them here.
 *
 * Throws when an unregistered class is requested (fail fast -- misconfiguration
 * is caught at the first request rather than silently returning a wrong factory).
 */
final readonly class EntityFactoryFactory implements EntityFactoryFactoryInterface
{
    /**
     * @param ServiceLocator<ServiceLocator<EntityFactoryInterface>> $locator
     */
    public function __construct(
        private ServiceLocator $locator,
    ) {
    }

    public function get(string $entityClass): EntityFactoryInterface
    {
        if ($this->has($entityClass)) {
            return $this->locator->get($entityClass)->get($entityClass);
        }

        throw new InvalidArgumentException("No EntityFactory registered for '$entityClass'.");
    }

    public function has(string $entityClass): bool
    {
        return $this->locator->has($entityClass) && $this->locator->get($entityClass)->has($entityClass);
    }
}
