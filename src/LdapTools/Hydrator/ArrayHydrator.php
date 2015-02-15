<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LdapTools\Hydrator;

/**
 * Hydrates a LDAP entry in an easier to use array form.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class ArrayHydrator implements HydratorInterface
{
    use HydratorTrait;
}
