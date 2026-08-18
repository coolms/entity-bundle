<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\ApiPlatform\Resource\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CoolMS\Entity\Field\EntityFieldDescriptorInterface;
use CoolMS\Entity\Field\FieldDescriptor;
use CoolMS\Entity\Field\FilterFieldContributorInterface;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;
use CoolMS\Entity\VirtualField\VirtualFieldRegistryInterface;
use CoolMS\EntityBundle\ApiPlatform\Resource\EntityFieldsResource;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase X-2.5 — Provider behind `GET /api/v1/entity/{alias}/filters`.
 *
 * Resolves the URL alias to its FQCN via
 * `EntityAliasRegistryInterface` (the same Phase 2 dictionary the
 * template-validation pipeline uses) and asks
 * `EntityFieldDescriptorInterface` for the descriptor list. 404
 * when the alias is unknown — matches the wizard's "entity-type
 * picker" contract: the frontend should only ever pass aliases it
 * just received from `/api/v1/entity/types` (or an equivalent
 * dictionary endpoint).
 *
 * Phase X-2.5b -- additionally surfaces virtual (computed) fields
 * via `VirtualFieldRegistryInterface`. Returned alongside stored
 * fields under the resource's `virtualFields` slot; empty list
 * when no virtual fields are registered for the alias.
 *
 * @implements ProviderInterface<EntityFieldsResource>
 */
final readonly class EntityFieldsProvider implements ProviderInterface
{
    /**
     * @param iterable<FilterFieldContributorInterface> $contributors
     */
    public function __construct(
        private EntityAliasRegistryInterface $aliasRegistry,
        private EntityFieldDescriptorInterface $descriptor,
        private VirtualFieldRegistryInterface $virtualFieldRegistry,
        private iterable $contributors = [],
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EntityFieldsResource
    {
        $alias = (string) ($uriVariables['alias'] ?? '');
        $fqcn = $this->aliasRegistry->resolve($alias);
        if (null === $fqcn) {
            throw new NotFoundHttpException(sprintf('Entity alias "%s" is not registered.', $alias));
        }

        return new EntityFieldsResource(
            alias: $alias,
            entityType: new ReflectionClass($fqcn)->getShortName(),
            fields: $this->mergeContributed($alias, $fqcn),
            virtualFields: $this->virtualFieldRegistry->getForEntity($alias),
        );
    }

    /**
     * Entity-declared fields first, then anything contributed that the entity
     * does not already declare.
     *
     * The entity's own `#[FieldMeta]` wins on a name collision — it is the
     * more specific statement, and silently letting a grid column override it
     * would make the endpoint's answer depend on service iteration order.
     *
     * @param class-string $fqcn
     *
     * @return list<FieldDescriptor>
     */
    private function mergeContributed(string $alias, string $fqcn): array
    {
        $fields = $this->descriptor->describe($fqcn);

        $seen = [];
        foreach ($fields as $field) {
            $seen[$field->field] = true;
        }

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->contribute($alias, $fqcn) as $extra) {
                if (isset($seen[$extra->field])) {
                    continue;
                }
                $seen[$extra->field] = true;
                $fields[] = $extra;
            }
        }

        return $fields;
    }
}
