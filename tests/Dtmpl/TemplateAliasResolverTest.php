<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Dtmpl;

use CoolMS\Core\Attribute\ClassMeta;
use CoolMS\Entity\Bundle\Dtmpl\TemplateAliasResolver;
use CoolMS\Entity\Registry\EntityAliasRegistryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TemplateAliasResolverTest extends TestCase
{
    #[Test]
    public function resolvesThroughTheRegistry(): void
    {
        $resolver = new TemplateAliasResolver($this->registry(
            resolvable: ['user' => stdClass::class, 'users' => stdClass::class],
            singular: ['user' => stdClass::class],
        ));

        self::assertSame(stdClass::class, $resolver->resolve('user'));
        self::assertSame(stdClass::class, $resolver->resolve('users'), 'a collection alias resolves too');
        self::assertNull($resolver->resolve('ghost'));
    }

    #[Test]
    public function knownAliasesAreTheSingularOnes(): void
    {
        $resolver = new TemplateAliasResolver($this->registry(
            resolvable: ['user' => stdClass::class, 'users' => stdClass::class],
            singular: ['user' => stdClass::class],
        ));

        self::assertSame(['user'], $resolver->knownAliases());
    }

    /**
     * @param array<string, class-string> $resolvable what `resolve()` admits
     * @param array<string, class-string> $singular   what `all()` lists
     */
    private function registry(array $resolvable, array $singular): EntityAliasRegistryInterface
    {
        return new class($resolvable, $singular) implements EntityAliasRegistryInterface {
            /**
             * @param array<string, class-string> $resolvable
             * @param array<string, class-string> $singular
             */
            public function __construct(
                private readonly array $resolvable,
                private readonly array $singular,
            ) {
            }

            public function resolve(string $alias): ?string
            {
                return $this->resolvable[$alias] ?? null;
            }

            public function aliasOf(string $entityType): ?string
            {
                $alias = array_search($entityType, $this->singular, true);

                return false === $alias ? null : $alias;
            }

            public function has(string $alias): bool
            {
                return isset($this->resolvable[$alias]);
            }

            public function all(): array
            {
                return $this->singular;
            }

            public function findByAlias(string $alias): ?ClassMeta
            {
                return null;
            }

            public function isCollectionAlias(string $alias): bool
            {
                return isset($this->resolvable[$alias]) && !isset($this->singular[$alias]);
            }
        };
    }
}
