<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Entity\Bundle\DependencyInjection\Compiler\TemplateWidgetSwitchPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;

/**
 * Stands in for dtmpl's WidgetRegistryPass: it records the service ids that
 * pass would collect into the widget registry, at the point it runs.
 */
final class CollectedWidgets implements CompilerPassInterface
{
    /** @var list<string> */
    public array $ids = [];

    public function process(ContainerBuilder $container): void
    {
        $this->ids = array_keys($container->findTaggedServiceIds(TemplateWidgetSwitchPass::WIDGET_TAG));
    }
}
