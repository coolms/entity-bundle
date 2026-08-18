<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use CoolMS\Entity\Registry\EntityAliasRegistry;
use CoolMS\Entity\Service\EntitySchemaLookup;
use Exception;
use InvalidArgumentException;

readonly class ExtrasPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private PropertyMetadataFactoryInterface $delegate,
        private EntitySchemaLookup $schemaLookup,
        private EntityAliasRegistry $aliasRegistry,
    ) {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        // Trying to get the metadata from the decorated factory
        try {
            $propertyMetadata = $this->delegate->create($resourceClass, $property, $options);
        } catch (Exception) {
            $propertyMetadata = new ApiProperty();
        }
        // If the class is not an aliased ExtrasProvider, return the metadata as is
        if (!$this->aliasRegistry->hasAlias($resourceClass)) {
            return $propertyMetadata;
        }
        // Check if the property is defined in the schema
        try {
            $alias = $this->aliasRegistry->getAlias($resourceClass);
        } catch (InvalidArgumentException) {
            // Concrete class not in registry -- return metadata as-is
            return $propertyMetadata;
        }
        $fields = $this->schemaLookup->getSchemaForEntity($alias);

        if (isset($fields[$property])) {
            $fieldConfig = $fields[$property];

            $schema = ['type' => $this->mapTypeToOpenApi($fieldConfig['type'])];
            // Only include 'example' when a real value is defined; an explicit null
            // would override Swagger UI's default preview and show null for every field.
            if (isset($fieldConfig['example'])) {
                $schema['example'] = $fieldConfig['example'];
            }

            // Configure Swagger display
            return $propertyMetadata
                ->withDescription($fieldConfig['label'] ?? '')
                ->withReadable(true)
                ->withWritable(true)
                ->withRequired($fieldConfig['isRequired'] ?? false)
                ->withSchema($schema);
        }

        return $propertyMetadata;
    }

    private function mapTypeToOpenApi(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'number',
            default => 'string',
        };
    }
}
