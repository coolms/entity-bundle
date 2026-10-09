<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Dtmpl\Validation\AliasResolverInterface;
use CoolMS\Entity\Bundle\DependencyInjection\Extension;
use CoolMS\Entity\Bundle\Dtmpl\TemplateAliasResolver;
use CoolMS\Entity\Security\NoRecordIsReadable;
use CoolMS\Entity\Security\RecordReadGuardInterface;
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

    /**
     * Every template read of a record asks the read guard, and the resolver and widgets that ask it are autowired:
     * without this alias they would not build, and with a permissive default a host that declares nothing would
     * render every record in full. The default refuses; a host points the alias at its own guard.
     */
    #[Test]
    public function theReadGuardDefaultsToOneThatRefusesEveryRecord(): void
    {
        $container = new ContainerBuilder();
        new Extension()->load([[]], $container);

        self::assertTrue($container->hasDefinition(NoRecordIsReadable::class));
        self::assertTrue($container->hasAlias(RecordReadGuardInterface::class));
        self::assertSame(NoRecordIsReadable::class, (string) $container->getAlias(RecordReadGuardInterface::class));
    }
}
