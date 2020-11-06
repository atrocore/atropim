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

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Treo\Core\Utils\Util;

/**
 * Class ProductFamilyAttribute
 */
class ProductFamilyAttribute extends Base
{
    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return bool|void
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        // exit
        if (!empty($options['skipValidation'])) {
            return true;
        }

        // when customer try to create records for few channels
        $this->prepareForChannels($entity);

        // is valid
        $this->isValid($entity);

        // clearing channel id if it needs
        if ($entity->get('scope') == 'Global') {
            $entity->set('channelId', null);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, array $options = [])
    {
        // update product attribute values
        $this->updateProductAttributeValues($entity);

        parent::afterSave($entity, $options);

        // when customer try to create records for few channels
        if (!empty($entity->tmpChannelsId)) {
            foreach ($entity->tmpChannelsId as $channelId) {
                $newEntity = $this->get();
                $newEntity->set($entity->toArray());
                $newEntity->id = Util::generateId();
                $newEntity->set('channelId', $channelId);

                try {
                    $this->getEntityManager()->saveEntity($newEntity);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("ProductFamilyAttribute ERROR: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function afterRemove(Entity $entity, array $options = [])
    {
        $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->removeCollectionByProductFamilyAttribute($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    /**
     * @param Entity $entity
     *
     * @throws BadRequest
     */
    protected function isValid(Entity $entity): void
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('attributeId')) {
            throw new BadRequest($this->exception('Attribute inherited from product family cannot be changed.'));
        }

        if (empty($entity->get('productFamilyId')) || empty($entity->get('attributeId'))) {
            throw new BadRequest($this->exception('ProductFamily and Attribute cannot be empty'));
        }

        if (!$this->isUnique($entity)) {
            throw new BadRequest($this->exception($this->createUnUniqueValidationMessage($entity, $entity->get('channelId'))));
        }
    }

    /**
     * @param Entity $entity
     *
     * @throws BadRequest
     */
    protected function prepareForChannels(Entity $entity)
    {
        if (empty($entity->get('channelId')) && !empty($channelsIds = $entity->get('channelsIds'))) {
            // find exists records
            $exists = $this
                ->select(['channelId'])
                ->where(
                    [
                        'productFamilyId' => $entity->get('productFamilyId'),
                        'attributeId'     => $entity->get('attributeId'),
                        'scope'           => 'Channel',
                        'channelId'       => $channelsIds
                    ]
                )
                ->find()
                ->toArray();
            $exists = array_column($exists, 'channelId');

            $notExistsChannelIds = [];
            foreach ($channelsIds as $channelId) {
                if (!in_array($channelId, $exists)) {
                    $notExistsChannelIds[] = $channelId;
                }
            }

            if (empty($notExistsChannelIds)) {
                throw new ProductAttributeAlreadyExists($this->createUnUniqueValidationMessage($entity, array_shift($channelsIds)));
            }

            $entity->set('channelId', array_shift($notExistsChannelIds));
            $entity->set('channelsIds', null);
            $entity->set('channelsNames', !empty($exists));
            if (!empty($notExistsChannelIds)) {
                $entity->tmpChannelsId = $notExistsChannelIds;
            }
        }
    }

    /**
     * @param Entity      $entity
     * @param string|null $channelId
     *
     * @return string
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function createUnUniqueValidationMessage(Entity $entity, string $channelId = null): string
    {
        $channelName = $entity->get('scope');
        if ($channelName == 'Channel') {
            $channel = $this->getEntityManager()->getEntity('Channel', $channelId);
            $channelName = !empty($channel) ? "'" . $channel->get('name') . "'" : '';
        }

        $message = $this->translate('productAttributeAlreadyExists', 'exceptions', 'ProductAttributeValue');
        $message = sprintf($message, $entity->get('attribute')->get('name'), $channelName);

        return $message;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        $where = [
            'id!='            => $entity->get('id'),
            'productFamilyId' => $entity->get('productFamilyId'),
            'attributeId'     => $entity->get('attributeId'),
            'scope'           => $entity->get('scope'),
        ];
        if ($entity->get('scope') == 'Channel') {
            $where['channelId'] = $entity->get('channelId');
        }

        $item = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['id'])
            ->where($where)
            ->findOne();

        return empty($item);
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function updateProductAttributeValues(Entity $entity): bool
    {
        // get products ids
        if (empty($productsIds = $entity->get('productFamily')->get('productsIds'))) {
            return true;
        }

        // get already exists
        $exists = $this->getExistsProductAttributeValues($entity, $productsIds);

        // get product family attribute id
        $pfaId = $entity->get('id');

        // get scope
        $scope = (string)$entity->get('scope');

        // get channel id
        $channelId = (string)$entity->get('channelId');

        // get is required param
        $isRequired = (int)$entity->get('isRequired');

        // get attribute id
        $attributeId = $entity->get('attributeId');

        // Link exists records to product family attribute if it needs
        $skipToCreate = [];
        foreach ($exists as $item) {
            // prepare id
            $id = $item['id'];

            if (empty($item['productFamilyAttributeId']) && (string)$item['scope'] == $scope && (string)$item['channelId'] == $channelId) {
                if ($entity->isNew()) {
                    $skipToCreate[] = $item['productId'];
                    $this->execute("UPDATE product_attribute_value SET product_family_attribute_id='$pfaId',is_required=$isRequired WHERE id='$id'");
                } else {
                    $this->execute("UPDATE product_attribute_value SET deleted=1 WHERE id='$id'");
                }
            }
        }

        // Update exists records if it needs
        if (!$entity->isNew()) {
            $this->execute(
                "UPDATE product_attribute_value SET is_required=$isRequired,scope='$scope',channel_id='$channelId' WHERE product_family_attribute_id='$pfaId' AND deleted=0"
            );
        }

        // Create a new records if it needs
        if ($entity->isNew()) {
            // prepare data
            $createdById = 'system';
            $createdAt = $entity->get('createdAt');

            foreach ($productsIds as $productId) {
                if (in_array($productId, $skipToCreate)) {
                    continue 1;
                }

                // generate id
                $id = Util::generateId();

                $this->execute(
                    "INSERT INTO product_attribute_value (id,scope,product_id,attribute_id,product_family_attribute_id,created_by_id,created_at,is_required,channel_id) VALUES ('$id','$scope','$productId','$attributeId','$pfaId','$createdById','$createdAt',$isRequired,'$channelId')"
                );
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        $this->addDependency('language');
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, string $scope = 'ProductFamilyAttribute'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions');
    }

    /**
     * @param Entity $entity
     * @param array  $productsIds
     *
     * @return array
     */
    private function getExistsProductAttributeValues(Entity $entity, array $productsIds): array
    {
        return $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $productsIds, 'attributeId' => $entity->get('attributeId')])
            ->find()
            ->toArray();
    }

    /**
     * @param string $sql
     */
    private function execute(string $sql)
    {
        try {
            $this->getEntityManager()->nativeQuery($sql);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("SQL ERROR: '$sql' -> " . $e->getMessage());
        }
    }
}
