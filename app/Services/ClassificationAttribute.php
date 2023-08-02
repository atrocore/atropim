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

            if($attribute->get('type') === 'extensibleMultiEnum') {
                if(!empty($entity->get('default'))){
                    $entity->set('default', json_decode($entity->get('default')));
                    $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                        ->where(['id' => $entity->get('default')])->find();
                    $entity->set('defaultNames', array_column($options->toArray(), 'name'));
                    $entity->set('default', $entity->get('defaultNames'));
                }
            }else if($attribute->get('type') === 'array'){
                if(!empty($entity->get('default'))){
                    $entity->set('default', json_decode($entity->get('default')));
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
         * Prepare maxLength and isRequired
         */
        if (!property_exists($attachment, 'maxLength')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($attachment->attributeId);
            if (empty($attribute)) {
                throw new BadRequest("Attribute '$attachment->attributeId' does not exist.");
            }
            if (!isset($attachment->isRequired)) {
                $attachment->isRequired = $attribute->get('isRequired');
            }
            if (in_array($attribute->get('type'), ['varchar', 'text', 'wysiwyg']) && $attribute->get('maxLength') !== null) {
                $attachment->maxLength = $attribute->get('maxLength');
                $attachment->countBytesInsteadOfCharacters = $attribute->get('countBytesInsteadOfCharacters');
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

        if (!property_exists($data, 'isRequired')) {
            $data->isRequired = !empty($attribute->get('isRequired'));
        }

        if (!property_exists($data, 'scope')) {
            $data->scope = $attribute->get('defaultScope') ?? 'Global';
            if ($data->scope === 'Channel') {
                if (!empty($attribute->get('defaultChannelId'))) {
                    $data->channelId = $attribute->get('defaultChannelId');
                } else {
                    $data->scope = 'Global';
                }
            }
        }

        if (!property_exists($data, 'maxLength')) {
            $data->maxLength = $attribute->get('maxLength');
            $data->countBytesInsteadOfCharacters = $attribute->get('countBytesInsteadOfCharacters');
        }
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data): void
    {
        if (!property_exists($data, 'classificationId')) {
            return;
        }

        foreach ($this->getRepository()->getProductChannelsViaClassificationId($data->classificationId) as $id) {
            $inputData = clone $data;
            $inputData->productId = $id;
            unset($inputData->classificationId);

            $parentId = $this->getPseudoTransactionManager()->pushCreateEntityJob('ProductAttributeValue', $inputData);
            $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $inputData->productId, null, $parentId);
        }
    }

    public function updateEntity($id, $data)
    {
        $inTransaction = false;
        if (property_exists($data, 'default') && is_array($data->default) && !empty($data->default)) {
            $data->default = json_encode($data->default);
        }

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
        $pavs = $this->getRepository()->getInheritedPavs($id);
        foreach ($this->getRepository()->getInheritedPavs($id) as $pav) {
            $inputData = new \stdClass();
            foreach (['scope', 'channelId', 'language'] as $key) {
                if (property_exists($data, $key)) {
                    $inputData->$key = $data->$key;
                }
            }

            if (!empty((array)$inputData)) {
                $parentId = $this->getPseudoTransactionManager()->pushUpdateEntityJob('ProductAttributeValue', $pav->get('id'), $inputData);
                $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $pav->get('productId'), null, $parentId);
            }

            $isChange = $this->getEntityManager()->getRepository('ProductAttributeValue')->setSpecificValue($pav, $this->getDefaultValueToSet($pav, $data));
            if($isChange){
                $this->getEntityManager()->saveEntity($pav);
            }
        }
    }

    public function getDefaultValueToSet($pav, $data)
    {
        $type = $pav->get('attributeType');

        if($type === 'asset'){
            return [$data->defaultId];
        }else if(in_array($type, ['rangeInt', 'rangeFloat'])){
            return [$data->defaultFrom, $data->defaultTo];
        }else if($type === 'currency'){
            return [$data->default, $data->defaultCurrency];
        }else if(in_array($type, ['extensibleMultiEnum', 'array'])){
            return [json_encode($data->default)];
        }
        else{
            return [$data->default];
        }
    }

    public function deleteEntityWithThemPavs($id)
    {
        /**
         * ID can be an array with one item. It is needs to execute this method from custom pseudo transaction in advanced classification module
         */
        if (is_array($id)) {
            $id = array_shift($id);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->withPavs = true;
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
        foreach ($this->getRepository()->getInheritedPavs($id) as $pav) {
            $parentId = $this->getPseudoTransactionManager()->pushDeleteEntityJob('ProductAttributeValue', $pav->get('id'));
            $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $pav->get('productId'), null, $parentId);
        }
    }
}
