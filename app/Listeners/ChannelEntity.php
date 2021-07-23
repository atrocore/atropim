<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions;
use Espo\ORM\Entity;
use Pim\Entities\Channel;
use Treo\Core\EventManager\Event;

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
    public function beforeSave(Event $event)
    {
        /** @var Channel $entity */
        $entity = $event->getArgument('entity');

        if (!$this->isCodeValid($entity)) {
            throw new Exceptions\BadRequest(
                $this->translate(
                    'codeIsInvalid',
                    'exceptions',
                    'Global'
                )
            );
        }
    }

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

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        /** @var Entity|string $foreign */
        $foreign = $event->getArgument('foreign');

        if ($event->getArgument('relationName') == 'products') {
            $this->getEntityManager()->getRepository('Product')->isChannelAlreadyRelated($foreign, $entity);
        }
    }
}
