<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Factory;

use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Core\Service\DataFormat;
use CoolMS\Entity\Factory\EntityFactoryInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Entity Factory.
 *
 * Responsible for hydrating entities from plain arrays / JSON / XML (via Symfony Denormalizer->denormalize)
 * and serializing entities back to arrays / JSON / XML.
 *
 * This class intentionally has NO persistence logic. Callers must inject the
 * appropriate RepositoryInterface and call repo->save($entity) explicitly.
 *
 * Named services are registered per entity class in each bundle's Extension:
 *   coolms.<module>.entity_factory.<entity>
 */
final readonly class EntityFactory implements EntityFactoryInterface
{
    /**
     * @param class-string<IdentifierProviderInterface> $objectClass          the entity class (or interface -- see {@see resolveClass()}) this factory hydrates
     * @param list<string>                              $defaultProcessGroups
     * @param list<string>                              $defaultProvideGroups
     */
    public function __construct(
        public string $objectClass,
        private SerializerInterface&NormalizerInterface&DecoderInterface $serializer,
        private DenormalizerInterface $denormalizer,
        private ParameterBagInterface $parameterBag,
        private array $defaultProcessGroups = [],
        private array $defaultProvideGroups = [],
    ) {
    }

    /**
     * Hydrate a new entity from an array using Symfony Serializer denormalize().
     *
     * Does NOT persist -- callers must call repo->save($entity) after this.
     *
     * Supported context keys (in addition to all standard Symfony Serializer keys):
     *    - target_class: string -- override the concrete class to instantiate
     *
     * @param array<string, mixed>|string $data
     * @param array<string, mixed>        $context
     *
     * @throws ExceptionInterface
     */
    public function process(array|string $data, ?DataFormat $format = null, array $context = []): IdentifierProviderInterface
    {
        if (null === $format && is_string($data)) {
            throw new InvalidArgumentException('Format must be specified when processing raw string data (JSON, XML, YAML, etc.).');
        }
        if ($this->defaultProcessGroups) {
            $context['groups'] ??= $this->defaultProcessGroups;
        }
        $targetClass = $this->resolveClass($context['target_class'] ?? $this->objectClass);
        // Strip internal keys before handing context to the serializer
        unset($context['target_class']);
        // Decode string input to an array first; the denormalizer works on arrays, not encoded strings.
        $normalizedData = is_string($data)
            ? $this->serializer->decode($data, $format->value, $context)
            : $data;

        return $this->denormalizer->denormalize($normalizedData, $targetClass, null, $context);
    }

    /**
     * Alias for process() -- returns a hydrated entity WITHOUT persisting it.
     *
     * Sets createdAt / updatedAt / accessedAt on TimestampableInterface entities.
     *
     * @param array<string, mixed>|string $data
     * @param array<string, mixed>        $context
     *
     * @throws ExceptionInterface
     */
    public function create(array|string $data, ?DataFormat $format = null, array $context = []): IdentifierProviderInterface
    {
        return $this->process($data, $format, $context);
    }

    /**
     * Patch an existing entity from an array using Symfony Serializer denormalize()
     * with the AbstractNormalizer::OBJECT_TO_POPULATE context key.
     *
     * Does NOT persist -- callers must call repo->save($entity) after this.
     *
     * @param array<string, mixed>|string $data
     * @param array<string, mixed>        $context
     *
     * @throws ExceptionInterface
     */
    public function update(array|string $data, ?DataFormat $format = null, array $context = []): IdentifierProviderInterface
    {
        if (empty($context[AbstractNormalizer::OBJECT_TO_POPULATE])) {
            throw new InvalidArgumentException(AbstractNormalizer::OBJECT_TO_POPULATE . ' context key is required');
        }
        $entity = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
        if (!is_a($entity, $this->objectClass)) {
            throw new InvalidArgumentException("Object must be an instance of $this->objectClass");
        }
        if (null === $format && is_string($data)) {
            throw new InvalidArgumentException('Format must be specified when updating from raw string data (JSON, XML, YAML, etc.).');
        }
        // Decode string input to an array first; the denormalizer works on arrays, not encoded strings.
        $normalizedData = is_string($data)
            ? $this->serializer->decode($data, $format->value, $context)
            : $data;

        return $this->denormalizer->denormalize($normalizedData, $entity::class, null, $context);
    }

    /**
     * @throws ExceptionInterface
     */
    public function provide(mixed $data, DataFormat $format = DataFormat::JSON, array $context = []): array|string
    {
        if ($this->defaultProvideGroups) {
            $context['groups'] ??= $this->defaultProvideGroups;
        }
        if (DataFormat::ARRAY === $format) {
            /** @var array<string, mixed> $normalized */
            $normalized = $this->serializer->normalize($data, $format->value, $context);

            return $normalized;
        }

        return $this->serializer->serialize($data, $format->value, $context);
    }

    /**
     * Resolve interface to a concrete class using the coolms.mapping.resolve_target_entities container parameter.
     * If $class is not an interface, it is returned as-is.
     *
     * @param class-string<IdentifierProviderInterface> $class
     *
     * @return class-string<IdentifierProviderInterface>
     */
    private function resolveClass(string $class): string
    {
        if (!interface_exists($class)) {
            return $class;
        }
        $resolveMap = $this->parameterBag->get('coolms.mapping.resolve_target_entities');
        /** @var array<class-string<IdentifierProviderInterface>, class-string<IdentifierProviderInterface>> $resolveMap */
        $resolveMap = is_array($resolveMap) ? $resolveMap : [];

        return $resolveMap[$class] ?? $class;
    }
}
