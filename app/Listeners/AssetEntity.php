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
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Treo\Core\EventManager\Event;
use Espo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class AssetEntity
 */
class AssetEntity extends AbstractListener
{
    public function beforeSave(Event $event): void
    {
        /** @var Asset $asset */
        $asset = $event->getArgument('entity');

        if (
            !$asset->isNew()
            && !empty($asset->get('entityName'))
            && !empty($asset->get('entityId'))
            && in_array($asset->get('entityName'), ['Category', 'Product'])
            && $this->isAttributeChannelChanged($asset)
        ) {
            $entity = $this
                ->getEntityManager()
                ->getEntity($asset->get('entityName'), $asset->get('entityId'));

            if ($asset->get('entityName') === 'Category') {
                if ($entity->get('imageId') === $asset->get('fileId')) {
                    throw new BadRequest($this->getLanguage()->translate("scopeForTheImageMarkedAsMainCannotBeChanged", 'exceptions', 'Asset'));
                }
            }

            if ($asset->get('entityName') === 'Product') {
                foreach ($entity->getMainImages() as $image) {
                    if ($image['attachmentId'] === $asset->get('fileId')) {
                        throw new BadRequest($this->getLanguage()->translate("scopeForTheImageMarkedAsMainCannotBeChanged", 'exceptions', 'Asset'));
                    }
                }
            }
        }
    }

    public function afterSave(Event $event): void
    {
        /** @var Asset $asset */
        $asset = $event->getArgument('entity');

        if (empty($entityId = $asset->get('entityId')) || empty($entityName = $asset->get('entityName')) || $entityName !== 'Product') {
            return;
        }

        if (!$this->getEntityManager()->getRepository('Product')->isImage((string)$asset->get('fileName'))) {
            return;
        }

        if (empty($product = $this->getEntityManager()->getEntity('Product', $entityId))) {
            return;
        }

        if ($asset->isAttributeChanged('isMainImage')) {
            if (!empty($asset->get('isMainImage'))) {
                $product->addMainImage($asset->get('fileId'), null);
            } else {
                $product->removeMainImage(null);
            }
            $this->getEntityManager()->saveEntity($product);
        }

        if (empty($asset->get('isMainImage')) && $asset->isAttributeChanged('channels') && $asset->get('scope') == 'Global') {
            if (!empty($asset->get('channels'))) {
                foreach ($asset->get('channels') as $channelId) {
                    $product->addMainImage($asset->get('fileId'), $channelId);
                }
                $this->getEntityManager()->saveEntity($product);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event): void
    {
        $fileId = $event->getArgument('entity')->get('fileId');
        $this->getEntityManager()->nativeQuery("UPDATE category SET image_id=null WHERE image_id='$fileId'");
    }

    /**
     * @param Asset $asset
     *
     * @return bool
     */
    protected function isAttributeChannelChanged(Asset $asset): bool
    {
        $result = false;
        $table = Util::toCamelCase($asset->get('entityName'));

        $data = $this
            ->getEntityManager()
            ->nativeQuery("SELECT channel FROM {$table}_asset WHERE asset_id = '{$asset->get('id')}' AND {$table}_id = '{$asset->get('entityId')}' AND deleted = 0;")
            ->fetch(\PDO::FETCH_ASSOC);

        if ($data['channel'] != $asset->get('channelId')) {
            $result = true;
        }

        return $result;
    }
}
