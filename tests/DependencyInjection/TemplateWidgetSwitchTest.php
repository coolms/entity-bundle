<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\DependencyInjection;

use CoolMS\Dtmpl\Widget\WidgetRendererInterface;
use CoolMS\Entity\Application\Widget\EntityFindAllWidgetRenderer;
use CoolMS\Entity\Application\Widget\EntityFindWidgetRenderer;
use CoolMS\Entity\Bundle\DependencyInjection\Compiler\TemplateWidgetSwitchPass;
use CoolMS\Entity\Bundle\DependencyInjection\Configuration;
use CoolMS\Entity\Bundle\DependencyInjection\Extension;
use CoolMS\Entity\Bundle\EntityBundle;
use CoolMS\Entity\Bundle\Tests\Fixture\CollectedWidgets;
use CoolMS\Entity\Bundle\Tests\Fixture\GreetingWidgetRenderer;
use CoolMS\Entity\Bundle\Tests\Fixture\NoEntityAliases;
use CoolMS\Entity\Resolver\EntityAliasResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function sort;

/**
 * `{widget:entity:find}` and `{widget:entity:findAll}` reach dtmpl's widget
 * registry only when the application enables `entity.template_widgets`, and
 * the switch is off unless it does.
 *
 * The compiled cases run this bundle's own compiler passes at their own
 * priorities, with the registry's collection stood in for by a pass in the
 * slot dtmpl's WidgetRegistryPass takes (before optimisation, priority 0). The
 * switch's value, and what the extension registers when it is on, come from
 * this bundle's own Extension. The "application glob" cases register both
 * renderers autoconfigured, as a service glob over coolms/entity-application
 * does, so the switch is tested against the way they actually got in.
 */
final class TemplateWidgetSwitchTest extends TestCase
{
    #[Test]
    public function theSwitchIsOffUnlessTheApplicationTurnsItOn(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), []);

        self::assertFalse($config['template_widgets']);
    }

    #[Test]
    public function withTheSwitchOffNeitherEntityWidgetIsRegisteredEvenWhenAnApplicationGlobMadeThem(): void
    {
        self::assertSame([GreetingWidgetRenderer::class], $this->registeredWidgets(enabled: false, applicationGlob: true));
    }

    #[Test]
    public function withTheSwitchOffAndNoGlobNothingOfTheBundleIsRegistered(): void
    {
        self::assertSame([GreetingWidgetRenderer::class], $this->registeredWidgets(enabled: false, applicationGlob: false));
    }

    #[Test]
    public function withTheSwitchOnTheBundleRegistersBothItself(): void
    {
        $expected = [EntityFindAllWidgetRenderer::class, EntityFindWidgetRenderer::class, GreetingWidgetRenderer::class];
        sort($expected);

        self::assertSame($expected, $this->registeredWidgets(enabled: true, applicationGlob: false));
    }

    #[Test]
    public function withTheSwitchOnAnApplicationGlobChangesNothing(): void
    {
        $expected = [EntityFindAllWidgetRenderer::class, EntityFindWidgetRenderer::class, GreetingWidgetRenderer::class];
        sort($expected);

        self::assertSame($expected, $this->registeredWidgets(enabled: true, applicationGlob: true));
    }

    /**
     * The service ids carrying the widget tag where dtmpl's registry collects
     * them, sorted.
     *
     * @return list<string>
     */
    private function registeredWidgets(bool $enabled, bool $applicationGlob): array
    {
        $bundle = new ContainerBuilder();
        new Extension()->load($enabled ? [['template_widgets' => true]] : [], $bundle);

        $container = new ContainerBuilder();
        // What dtmpl's bundle provides: every widget renderer it finds is tagged.
        $container->registerForAutoconfiguration(WidgetRendererInterface::class)
            ->addTag(TemplateWidgetSwitchPass::WIDGET_TAG);
        $container->register(EntityAliasResolverInterface::class, NoEntityAliases::class);
        $container->register(GreetingWidgetRenderer::class)->setAutoconfigured(true);
        if ($applicationGlob) {
            foreach (TemplateWidgetSwitchPass::RENDERERS as $renderer) {
                $container->register($renderer)->setAutowired(true)->setAutoconfigured(true);
            }
        }

        // From this bundle's own extension: the switch, and what it registers when on.
        $container->setParameter(
            TemplateWidgetSwitchPass::PARAMETER,
            $bundle->getParameter(TemplateWidgetSwitchPass::PARAMETER),
        );
        foreach (TemplateWidgetSwitchPass::RENDERERS as $renderer) {
            if ($bundle->hasDefinition($renderer)) {
                $container->setDefinition($renderer, $bundle->getDefinition($renderer));
            }
        }

        // Added before the bundle's own passes, at the registry's priority: at
        // an equal priority it runs first, so only a higher priority puts the
        // switch ahead of it.
        $registry = new CollectedWidgets();
        $container->addCompilerPass($registry, PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        new EntityBundle()->build($container);
        $container->compile();

        $ids = $registry->ids;
        sort($ids);

        return $ids;
    }
}
