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
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class ProductFamilyAttribute
 */
class ProductFamilyAttribute extends Base
{
    const UPDATE_PFA_FILE_PATH = 'data/update-pfa.json';

    public static function getUpdatePfaData(): array
    {
        return !empty($content = @file_get_contents(self::UPDATE_PFA_FILE_PATH)) ? Json::decode($content, true) : [];
    }

    public function actualizePfa(string $productId = null): void
    {
        $productId = $this
            ->getInjection('eventManager')
            ->dispatch('ProductFamilyAttributeRepository', 'actualizePfa', new Event(['productId' => $productId]))
            ->getArgument('productId');

        foreach (self::getUpdatePfaData() as $k => $v) {
            $parts = explode('_', $k);

            if (!empty($productId) && $parts[0] !== $productId) {
                continue;
            }

            $updated[] = $k;

            if (empty($pfa = $this->get($parts[1]))) {
                continue;
            }

            $pav = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->where(['productId' => $parts[0], 'attributeId' => $pfa->get('attributeId')])
                ->findOne();

            if (empty($pav)) {
                $pav = $this->getEntityManager()->getRepository('ProductAttributeValue')->get();
                $pav->set('productId', $parts[0]);
                $pav->set('attributeId', $pfa->get('attributeId'));
            }

            if ($pav->get('scope') == $pfa->get('scope') && $pav->get('channelId') == $pfa->get('channelId')) {
                $pav->set('productFamilyAttributeId', $pfa->get('id'));
                $pav->set('isRequired', $pfa->get('isRequired'));
            }

            if ($pav->isNew() || $pav->isAttributeChanged('productFamilyAttributeId') || $pav->isAttributeChanged('isRequired')) {
                $pav->skipPfValidation = true;
                $this->getEntityManager()->saveEntity($pav);
            }
        }

        if (empty($updated)) {
            return;
        }

        $data = self::getUpdatePfaData();
        foreach ($updated as $v) {
            if (isset($data[$v])) {
                unset($data[$v]);
            }
        }
        file_put_contents(self::UPDATE_PFA_FILE_PATH, Json::encode($data));
    }

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
            throw new BadRequest($this->exception('attributeInheritedFromProductFamilyCannotBeChanged'));
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

    protected function updateProductAttributeValues(Entity $entity): void
    {
        $products = $entity->get('productFamily')->get('products');

        $productsIds = [];
        foreach ($products as $product) {
            if ($product->get('type') === 'productVariant') {
                continue;
            }
            $productsIds[] = $product->get('id');
        }

        if (empty($productsIds)) {
            return;
        }

        $data = !empty($content = @file_get_contents(self::UPDATE_PFA_FILE_PATH)) ? Json::decode($content, true) : [];

        foreach ($productsIds as $productId) {
            $data["{$productId}_{$entity->get('id')}"] = true;
        }

        file_put_contents(self::UPDATE_PFA_FILE_PATH, Json::encode($data));
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
}
