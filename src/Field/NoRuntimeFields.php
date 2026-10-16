<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Field;

use CoolMS\Entity\Contract\FieldMetadataSourceInterface;
use CoolMS\Entity\Contract\FieldSchemaSourceInterface;

/**
 * The field-management half of an entity's schema when no package supplies one: no record-defined fields, no
 * per-property metadata, no file overrides -- for every alias and class.
 *
 * coolms/entity declares both ports and the Field family implements them, with one adapter. An application that
 * installs the entity packages without Field still compiles and runs -- every entity has only the fields its class and
 * configuration declare -- instead of failing on ports nothing binds. Bound by FieldSchemaSourceFallbackPass only for a
 * port no other package has bound.
 */
final readonly class NoRuntimeFields implements FieldSchemaSourceInterface, FieldMetadataSourceInterface
{
    public function getRuntimeFields(string $entityAlias): array
    {
        return [];
    }

    public function getRuntimeFieldSummaries(string $entityAlias): array
    {
        return [];
    }

    public function getResolvedFieldMetadata(string $className, string $entityAlias): array
    {
        return [];
    }

    public function hasFileOverride(string $entityAlias, string $fieldName): bool
    {
        return false;
    }
}
