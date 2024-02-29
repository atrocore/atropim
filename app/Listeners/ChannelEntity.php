<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Pim\Entities\Channel;

/**
 * Class ChannelEntity
 */
class ChannelEntity extends AbstractEntityListener
{
   /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        /** @var Channel $entity */
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('categoryId'))) {
            throw new BadRequest($this->translate('channelHasCategory', 'exceptions', 'Channel'));
        }

        if ($entity->get('products')->count() > 0) {
            throw new BadRequest($this->translate('channelHasProducts', 'exceptions', 'Channel'));
        }
    }
}
