<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\DependencyInjection\Compiler;

use CoolMS\Entity\Application\Widget\EntityFindAllWidgetRenderer;
use CoolMS\Entity\Application\Widget\EntityFindWidgetRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_column;
use function array_intersect;
use function in_array;
use function is_string;

/**
 * Keeps `{widget:entity:find}` and `{widget:entity:findAll}` out of the widget
 * registry unless the application turned `entity.template_widgets` on.
 *
 * Whether a site offers them is the application's explicit decision, made in
 * one place: this bundle's configuration. With the switch off, a definition of
 * either renderer keeps nothing that registers it as a widget, wherever it came
 * from -- an application's service glob over `coolms/entity-application`
 * included, since autoconfiguration tags every widget renderer it finds. With
 * the switch on, the extension registers both itself and this pass does
 * nothing.
 *
 * A pass, and a priority, for the order it has to run in: after
 * autoconfiguration has applied its tags (priority 100) and before dtmpl's
 * `WidgetRegistryPass` collects them (priority 0, both before optimisation).
 * A missing switch parameter reads as off.
 */
final class TemplateWidgetSwitchPass implements CompilerPassInterface
{
    public const string PARAMETER = 'coolms.entity.template_widgets';

    /** The tag dtmpl's widget registry is built from. */
    public const string WIDGET_TAG = 'dtmpl.widget';

    public const array RENDERERS = [EntityFindWidgetRenderer::class, EntityFindAllWidgetRenderer::class];

    public const array KEYS = [EntityFindWidgetRenderer::KEY, EntityFindAllWidgetRenderer::KEY];

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter(self::PARAMETER) && true === $container->getParameter(self::PARAMETER)) {
            return;
        }

        foreach ($container->findTaggedServiceIds(self::WIDGET_TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            $isEntityWidget = (is_string($class) && in_array($class, self::RENDERERS, true))
                || [] !== array_intersect(array_column($tags, 'key'), self::KEYS);
            if ($isEntityWidget) {
                $definition->clearTag(self::WIDGET_TAG);
            }
        }
    }
}
