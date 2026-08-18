<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use CoolMS\Entity\Registry\EntityAliasRegistry;
use CoolMS\Entity\Service\EntitySchemaLookup;
use InvalidArgumentException;

readonly class ExtrasPropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    public function __construct(
        private PropertyNameCollectionFactoryInterface $delegate,
        private EntitySchemaLookup $schemaLookup,
        private EntityAliasRegistry $aliasRegistry,
    ) {
    }

    /** @throws ResourceClassNotFoundException */
    public function create(string $resourceClass, array $options = []): PropertyNameCollection
    {
        $collection = $this->delegate->create($resourceClass, $options);

        if (!$this->aliasRegistry->hasAlias($resourceClass)) {
            return $collection;
        }

        try {
            $alias = $this->aliasRegistry->getAlias($resourceClass);
        } catch (InvalidArgumentException) {
            // Concrete class not in registry (e.g. DynamicRecord itself rather than a registered alias) -- nothing to add
            return $collection;
        }

        // Dotted field names (e.g. "locale.value") describe admin-UI column
        // paths into embedded value objects -- they are NOT standalone API
        // properties and MUST NOT be added to the PropertyNameCollection.
        // Otherwise the serializer resolves them via PropertyAccessor dot-
        // traversal, which breaks on items that already flatten the nested
        // object into a scalar (e.g. nested variants normalized as strings).
        $dynamicFields = array_filter(
            array_keys($this->schemaLookup->getSchemaForEntity($alias)),
            static fn (string $name): bool => !str_contains($name, '.'),
        );

        // Combine the existing properties with the dynamic ones
        return new PropertyNameCollection(array_merge(iterator_to_array($collection), $dynamicFields));
    }
}
