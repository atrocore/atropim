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

use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class AssetService
 */
class AssetService extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeGetEntity(Event $event): void
    {
        $parts = explode('_', $event->getArgument('id'));
        $event->setArgument('id', array_shift($parts));
    }

    /**
     * @param Event $event
     */
    public function beforeUpdateEntity(Event $event): void
    {
        $this->beforeGetEntity($event);
    }

    /**
     * @param Event $event
     */
    public function afterUpdateEntity(Event $event): void
    {
        $asset = $event->getArgument('entity');
        if (!empty($entityName = $asset->get('entityName')) && $entityName == 'Product') {
            $channelId = empty($asset->get('channelId')) || $asset->get('scope') == 'Global' ? "''" : "'" . $asset->get('channelId') . "'";
            try {
                $this
                    ->getEntityManager()
                    ->nativeQuery("UPDATE product_asset SET channel=$channelId WHERE id='{$event->getArgument('data')->relationId}'");
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('Updating of Product Asset relation failed. Message: ' . $e->getMessage());
            }
        }
    }
}
