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

use Dam\Entities\Asset;
use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class AssetEntity
 */
class AssetEntity extends AbstractListener
{
    /** @var array */
    protected $hasMainImage = ['Product', 'Category'];

    public function afterSave(Event $event): void
    {
        /** @var Asset $asset */
        $asset = $event->getArgument('entity');

        if (!empty($entityName = $asset->get('entityName')) && !empty($entityId = $asset->get('entityId')) && in_array($entityName, $this->hasMainImage)) {
            $table = Util::toCamelCase($entityName);
            $assetId = $asset->get('id');
            $channelId = empty($asset->get('channelId')) || $asset->get('scope') == 'Global' ? 'NULL' : "'" . $asset->get('channelId') . "'";
            $entityId = $asset->get('entityId');
            $this
                ->getEntityManager()
                ->nativeQuery("UPDATE {$table}_asset SET channel=$channelId WHERE asset_id='$assetId' AND {$table}_id='{$entityId}' AND deleted=0");
        }
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event): void
    {
        $fileId = $event->getArgument('entity')->get('fileId');
        foreach ($this->hasMainImage as $entity) {
            $table = Util::toCamelCase($entity);
            $this->getEntityManager()->nativeQuery("UPDATE $table SET image_id=null WHERE image_id='$fileId'");
        }
    }
}
