<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Dtmpl\Widget\WidgetRendererInterface;
use Stringable;

/**
 * A widget that has nothing to do with entities: the template widget switch
 * must leave it registered either way.
 */
final class GreetingWidgetRenderer implements WidgetRendererInterface
{
    public const string KEY = 'greeting';

    public string $key { get => self::KEY; }

    public function __invoke(array $context, array $params = []): ?Stringable
    {
        return null;
    }
}
