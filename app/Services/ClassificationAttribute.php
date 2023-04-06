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

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ClassificationAttribute extends AbstractProductAttributeService
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
        if (!property_exists($attachment, 'attributeId')) {
            throw new BadRequest("'attributeId' is required.");
        }

        /**
         * Prepare maxLength
         */
        if (!property_exists($attachment, 'maxLength')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($attachment->attributeId);
            if (empty($attribute)) {
                throw new BadRequest("Attribute '$attachment->attributeId' does not exist.");
            }
            if (in_array($attribute->get('type'), ['varchar', 'text', 'wysiwyg']) && $attribute->get('maxLength') !== null) {
                $attachment->maxLength = $attribute->get('maxLength');
            }
        }

        /**
         * For multiple creation via languages
         */
        $this->prepareDefaultLanguages($attachment);
        if (property_exists($attachment, 'languages') && !empty($attachment->languages)) {
            return $this->multipleCreateViaLanguages($attachment);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $this->prepareDefaultValues($attachment);
            $result = parent::createEntity($attachment);
            $this->createAssociatedFamilyAttribute($attachment, $attachment->attributeId);
            $this->createPseudoTransactionCreateJobs($attachment);

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
            $aData->classificationId = $attachment->classificationId;
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

    protected function prepareDefaultValues(\stdClass $data): void
    {
        if (property_exists($data, 'attributeId') && !empty($data->attributeId)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data->attributeId);
        }

        if (empty($attribute)) {
            return;
        }

        if (!property_exists($data, 'isRequired') && !property_exists($data, 'scope')) {
            $data->isRequired = $attribute->get('isRequired');
            $data->scope = $attribute->get('defaultScope');
            if ($data->scope === 'Channel') {
                $data->channelId = $attribute->get('defaultChannelId');
            }
        }

        if (!property_exists($data, 'maxLength')) {
            $data->maxLength = $attribute->get('maxLength');
        }
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data): void
    {
        if (!property_exists($data, 'classificationId')) {
            return;
        }

        $products = $this->getRepository()->getProductChannelsViaClassificationId($data->classificationId);

        foreach ($products as $id => $channels) {
            $inputData = clone $data;

            if ($data->scope === 'Global' || in_array($data->channelId, $channels)) {
                $inputData->productId = $id;
                unset($inputData->classificationId);

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
            foreach (['scope', 'channelId', 'language'] as $key) {
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

    public function sortCollection(EntityCollection $collection): void
    {
        $attributes = [];
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => array_column($collection->toArray(), 'attributeId')])->find() as $attribute) {
            $attributes[$attribute->get('id')] = $attribute;
        }

        $records = [];
        foreach ($collection as $k => $entity) {
            $row = [
                'entity'      => $entity,
                'channelName' => $entity->get('scope') === 'Global' ? '-9999' : $entity->get('channelName'),
                'language'    => $entity->get('language') === 'main' ? null : $entity->get('language')
            ];

            $attribute = $attributes[$entity->get('attributeId')];

            if (!empty($attribute->get('attributeGroupId'))) {
                $row['sortOrder'] = empty($attribute->get('sortOrderInAttributeGroup')) ? 0 : (int)$attribute->get('sortOrderInAttributeGroup');
            } else {
                $row['sortOrder'] = empty($attribute->get('sortOrderInProduct')) ? 0 : (int)$attribute->get('sortOrderInProduct');
            }

            $records[$k] = $row;
        }

        array_multisort(
            array_column($records, 'sortOrder'), SORT_ASC,
            array_column($records, 'channelName'), SORT_ASC,
            array_column($records, 'language'), SORT_ASC,
            $records
        );

        foreach ($records as $k => $record) {
            $collection->offsetSet($k, $record['entity']);
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->sortCollection($collection);

        parent::prepareCollectionForOutput($collection);
    }

    public function unlinkAttributeGroupHierarchy(string $attributeGroupId, string $classificationId): bool
    {
        $attributes = $this
            ->getRepository()
            ->select(['id'])
            ->join('attribute')
            ->where([
                'attribute.attributeGroupId' => $attributeGroupId,
                'classificationId'           => $classificationId
            ])
            ->find()
            ->toArray();

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                try {
                    $this->deleteEntity($attribute['id']);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('AttributeGroup hierarchical removing from Classification failed: ' . $e->getMessage());
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
