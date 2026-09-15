<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Dtmpl\Validation\AliasResolverInterface;
use CoolMS\Entity\Bundle\DependencyInjection\Extension;
use CoolMS\Entity\Bundle\Dtmpl\TemplateAliasResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionTest extends TestCase
{
    /**
     * The engine's validator takes the resolver as an optional argument,
     * so a missing alias is not a build error: the container compiles and
     * every `@alias` in a template is refused as unknown. Asserted here
     * because nothing else would notice.
     */
    #[Test]
    public function theTemplateAliasPortIsAnsweredFromTheRegistry(): void
    {
        $container = new ContainerBuilder();
        new Extension()->load([[]], $container);

        self::assertTrue($container->hasDefinition(TemplateAliasResolver::class));
        self::assertTrue($container->getDefinition(TemplateAliasResolver::class)->isAutowired());
        self::assertTrue($container->hasAlias(AliasResolverInterface::class));
        self::assertSame(
            TemplateAliasResolver::class,
            (string) $container->getAlias(AliasResolverInterface::class),
        );
    }
}
