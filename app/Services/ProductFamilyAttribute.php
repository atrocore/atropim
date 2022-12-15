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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

/**
 * Class ProductFamilyAttribute
 */
class ProductFamilyAttribute extends Base
{
    /**
     * @var array
     */
    protected $mandatorySelectAttributeList = ['scope', 'isRequired'];

    /**
     * @inheritDoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($attribute = $entity->get('attribute'))) {
            $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
            $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
            $entity->set('sortOrder', $attribute->get('sortOrder'));
            if (!empty($this->getConfig()->get('isMultilangActive'))) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
                    $entity->set('attributeName' . $preparedLocale, $attribute->get('name' . $preparedLocale));
                }
            }
        }
    }

    public function createEntity($attachment)
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->prepareDefaultValues($attachment);
            $attribute = $this->getEntityManager()->getEntity('Attribute', $attachment->attributeId);
            if ($attribute->get('isMultilang')) {
                $langs = $attachment->languages ?: array_merge($this->getConfig()->get('inputLanguageList', []), ['main']);
                if (!empty($attachment->channelId)) {
                    $channel = $this->getEntityManager()->getEntity('Channel', $attachment->channelId);
                    $langs = array_intersect($langs, $channel->get('locales'));
                }
                $attachments = [];
                foreach ($langs as $lang) {
                    $attach = clone $attachment;
                    $attach->language = $lang;
                    $attachments[] = $attach;
                }
            } else {
                $attachments = [$attachment];
            }

            foreach ($attachments as $attachment_clone) {
                $result = parent::createEntity($attachment_clone);
                $this->createAssociatedFamilyAttribute($attachment_clone, $attachment_clone->attributeId);
                $this->createPseudoTransactionCreateJobs($attachment_clone);
            }

            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function createAssociatedFamilyAttribute(\stdClass $attachment, string $attributeId): void
    {
        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($attributeId);
        if (empty($attribute)) {
            return;
        }

        $children = $attribute->get('children');
        if (empty($children) || count($children) === 0) {
            return;
        }

        foreach ($children as $child) {
            $aData = new \stdClass();
            $aData->attributeId = $child->get('id');
            $aData->productFamilyId = $attachment->productFamilyId;
            if (property_exists($attachment, 'ownerUserId')) {
                $aData->ownerUserId = $attachment->ownerUserId;
            }
            if (property_exists($attachment, 'assignedUserId')) {
                $aData->assignedUserId = $attachment->assignedUserId;
            }
            if (property_exists($attachment, 'teamsIds')) {
                $aData->teamsIds = $attachment->teamsIds;
            }
            $this->createEntity($aData);
        }
    }

    /**
     * @param $data
     *
     * @return void
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function prepareDefaultValues($data): void
    {
        if (!isset($data->isRequired) && !isset($data->scope)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data->attributeId);
            if ($attribute) {
                $data->isRequired = $attribute->get('defaultIsRequired');

                $defaultScope = $attribute->get('defaultScope');
                if ($defaultScope === 'Global') {
                    $data->scope = $defaultScope;
                } else {
                    $data->scope = $defaultScope;
                    $data->channelId = $attribute->get('defaultChannelId');
                    $data->channelName = $attribute->get('defaultChannelName');
                }
            }
        }
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data): void
    {
        if (!property_exists($data, 'productFamilyId')) {
            return;
        }

        $products = $this->getRepository()->getAvailableChannelsForPavs($data->productFamilyId);

        foreach ($products as $id => $channels) {
            $inputData = clone $data;

            if ($data->scope === 'Global' || in_array($data->channelId, $channels)) {
                $inputData->productId = $id;
                unset($inputData->productFamilyId);

                $this->getPseudoTransactionManager()->pushCreateEntityJob('ProductAttributeValue', $inputData);
            }
        }
    }

    public function updateEntity($id, $data)
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionUpdateJobs($id, clone $data);
            $result = parent::updateEntity($id, $data);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data): void
    {
        foreach ($this->getRepository()->getInheritedPavsIds($id) as $pavId) {
            $inputData = new \stdClass();
            foreach (['scope', 'channelId', 'isRequired'] as $key) {
                if (property_exists($data, $key)) {
                    $inputData->$key = $data->$key;
                }
            }

            if (!empty((array)$inputData)) {
                $this->getPseudoTransactionManager()->pushUpdateEntityJob('ProductAttributeValue', $pavId, $inputData);
            }
        }
    }

    public function deleteEntity($id)
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionDeleteJobs($id);
            $result = parent::deleteEntity($id);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @param string $attributeGroupId
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function unlinkAttributeGroupHierarchy(string $attributeGroupId, string $productFamilyId): bool
    {
        $attributes = $this
            ->getRepository()
            ->select(['id'])
            ->join('attribute')
            ->where([
                'attribute.attributeGroupId' => $attributeGroupId,
                'productFamilyId' => $productFamilyId
            ])
            ->find()
            ->toArray();

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                try {
                    $this->deleteEntity($attribute['id']);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('AttributeGroup hierarchical removing from ProductFamily failed: ' . $e->getMessage());
                }
            }
        }

        return true;
    }

    protected function createPseudoTransactionDeleteJobs(string $id): void
    {
        foreach ($this->getRepository()->getInheritedPavsIds($id) as $pavId) {
            $this->getPseudoTransactionManager()->pushDeleteEntityJob('ProductAttributeValue', $pavId);
        }
    }
}
