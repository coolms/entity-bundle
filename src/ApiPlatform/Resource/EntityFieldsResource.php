<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use CoolMS\Entity\Field\FieldDescriptor;
use CoolMS\Entity\Filter\VirtualFieldDescriptor;
use CoolMS\EntityBundle\ApiPlatform\Resource\Provider\EntityFieldsProvider;

/**
 * Phase X-2.5 — read-only resource exposing the filterable /
 * sortable / searchable field set for an entity alias.
 *
 *   GET /api/v1/entity/{alias}/filters
 *
 * Consumed by the document-generation wizard frontend (X-2.6) to
 * render filter UI dynamically per entity type. ROLE_ADMIN — the
 * wizard is admin-only and the descriptor reveals internal
 * structure that shouldn't leak to anonymous callers.
 *
 * Phase X-2.5b -- the `virtualFields` slot surfaces computed
 * (non-column) fields registered via `#[VirtualField]` or
 * `VirtualFieldProviderInterface`. Defaults to `[]` so pre-X-2.5b
 * consumers see the unchanged response shape.
 */
#[ApiResource(
    shortName: 'EntityFields',
    operations: [
        new Get(
            uriTemplate: '/entity/{alias}/filters',
            name: 'entity_fields_get',
            provider: EntityFieldsProvider::class,
        ),
    ],
    security: "is_granted('ROLE_ADMIN')",
)]
final readonly class EntityFieldsResource
{
    /**
     * @param list<FieldDescriptor>        $fields
     * @param list<VirtualFieldDescriptor> $virtualFields
     */
    public function __construct(
        public string $alias,
        public string $entityType,
        public array $fields,
        public array $virtualFields = [],
    ) {
    }
}
