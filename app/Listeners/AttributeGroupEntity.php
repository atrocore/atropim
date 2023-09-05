<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;

/**
 * Class AttributeGroupEntity
 */
class AttributeGroupEntity extends AbstractEntityListener
{
    /**
     * Before remove action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        if (count($event->getArgument('entity')->get('attributes')) > 0) {
            throw new BadRequest(
                $this->translate(
                    'attributeGroupSsLinkedWithAttributes',
                    'exceptions',
                    'AttributeGroup'
                )
            );
        }
    }
}
