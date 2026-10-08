<?php

declare(strict_types=1);

namespace CoolMS\Entity\Bundle\Tests\Fixture;

use CoolMS\Core\Identifier\IdentifierProviderInterface;

/**
 * An entity contract of a module, the kind of name a module registers an
 * entity factory under.
 */
interface ProductInterface extends IdentifierProviderInterface
{
}
