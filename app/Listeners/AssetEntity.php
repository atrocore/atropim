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
use Espo\Core\Utils\Json;
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

    /**
     * @param Event $event
     *
     * @throws BadRequest
     * @throws Error
     */
    public function beforeSave(Event $event): void
    {
        /** @var Asset $asset */
        $asset = $event->getArgument('entity');

        if (!$asset->isNew() && !empty($entityName = $asset->get('entityName'))
            && !empty($entityId = $asset->get('entityId')) && in_array($entityName, $this->hasMainImage)) {
            if ($this->isAttributeChannelChanged($asset)) {
                $table = Util::toCamelCase($entityName);

                $id = $this
                    ->getEntityManager()
                    ->nativeQuery("SELECT id FROM {$table} WHERE image_id = '{$asset->get('fileId')}' AND id = '{$entityId}'")
                    ->fetch(\PDO::FETCH_ASSOC);

                if (!empty($id)) {
                    throw new BadRequest(
                        $this
                            ->getLanguage()
                            ->translate("scopeForTheImageMarkedAsMainCannotBeChanged", 'exceptions', 'Asset')
                    );
                }
            }
        }
    }

    /**
     * @param Event $event
     *
     * @throws Error
     */
    public function afterSave(Event $event): void
    {
        /** @var Asset $asset */
        $asset = $event->getArgument('entity');

        if (!empty($entityId = $asset->get('entityId')) && !empty($entityName = $asset->get('entityName')) && $entityName == 'Product') {
            /** @var \Pim\Repositories\AbstractRepository $entityRepository */
            $entityRepository = $this->getEntityManager()->getRepository($entityName);

            if ($entityRepository->isImage($asset->get('file')->get('name'))) {
                /** @var \Pim\Entities\Product $entity */
                $entity = $this->getEntityManager()->getEntity($entityName, $entityId);

                if ($entity) {
                    if (!is_array($data = $entity->getDataField('mainImages'))) {
                        $data = [];
                    }

                    if ($asset->isAttributeChanged('isMainImage')) {
                        if ($asset->get('isMainImage')) {
                            $channels = $asset->get('scope') == 'Channel' ? [$asset->get('channelId')] : $asset->get('channels');

                            if ($asset->get('scope') == 'Global') {
                                $entity->set('imageId', $asset->get('fileId'));
                                if (isset($data[$asset->id])) {
                                    unset($data[$asset->id]);
                                    $entity->setDataField('mainImages', $data);
                                }

                                $this->getEntityManager()->saveEntity($entity);
                                return;
                            }

                            $data = $this->getService('Product')->getPreparedProductAssetData($data, $channels);
                            $data[$asset->id] = $channels;
                        } else {
                            if (isset($data[$asset->id])) {
                                unset($data[$asset->id]);
                            }

                            if ($asset->get('fileId') == $entity->get('imageId')) {
                                $entity->set('imageId', null);
                            }
                        }
                    }

                    if ($asset->isAttributeChanged('channels') && $asset->get('scope') == 'Global') {
                        $data = $this->getService('Product')->getPreparedProductAssetData($data, $asset->get('channels'));
                        $data[$asset->id] = $asset->get('channels');
                    }

                    $entity->setDataField('mainImages', $data);
                    $this->getEntityManager()->saveEntity($entity);
                }
            }
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
