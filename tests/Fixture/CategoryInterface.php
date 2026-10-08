<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Core\Identifier\IdentifierProviderInterface;

/**
 * A second entity contract of the same module, so that registering more than
 * one entity can be observed.
 */
interface CategoryInterface extends IdentifierProviderInterface
{
}
