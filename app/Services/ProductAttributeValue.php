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
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;

class ProductAttributeValue extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'language',
            'mainLanguageId',
            'productId',
            'productName',
            'attributeId',
            'attributeName',
            'attributeType',
            'intValue',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'varcharValue',
            'textValue'
        ];

    protected ?string $userLanguage = null;

    public function inheritPav(string $id): bool
    {
        $pav = $this->getEntity($id);
        $parentPav = $this->getRepository()->getParentPav($pav);
        if (empty($parentPav)) {
            return false;
        }
        $this->getRepository()->convertValue($parentPav);

        $input = new \stdClass();
        $input->value = $parentPav->get('value');

        switch ($parentPav->get('attributeType')) {
            case 'currency':
                $input->valueCurrency = $parentPav->get('valueCurrency');
            case 'unit':
                $input->valueUnit = $parentPav->get('valueUnit');
                break;
            case 'asset':
                $input->valueId = $parentPav->get('valueId');
                break;
        }

        $this->updateEntity($id, $input);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->prepareEntity($entity);
    }

    /**
     * @inheritDoc
     */
    public function createEntity($attachment)
    {
        $this->prepareInputValue($attachment);

        if ($this->isPseudoTransaction()) {
            return parent::createEntity($attachment);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::createEntity($attachment);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::createEntity($attachment);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = parent::createEntity($attachment);
            $this->createPseudoTransactionCreateJobs(clone $attachment);
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

    protected function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        if (!property_exists($data, 'productId')) {
            return;
        }

        $children = $this->getEntityManager()->getRepository('Product')->getChildrenArray($data->productId);
        foreach ($children as $child) {
            $inputData = clone $data;
            $inputData->productId = $child['id'];
            $inputData->productName = $child['name'];
            $transactionId = $this->getPseudoTransactionManager()->pushCreateEntityJob($this->entityType, $inputData, $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionCreateJobs(clone $inputData, $transactionId);
            }
        }
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        /**
         * Validate channel
         */
        if (
            !$this->isPseudoTransaction()
            && property_exists($data, 'channelId')
            && property_exists($data, 'productId')
            && property_exists($data, 'attributeId')
            && !empty($product = $this->getEntityManager()->getRepository('Product')->get($data->productId))
            && !in_array($data->channelId, $product->getLinkMultipleIdList('channels'))
        ) {
            $attributeName = property_exists($data, 'attributeName') ? $data->attributeName : $data->attributeId;
            $channelName = property_exists($data, 'channelName') ? $data->channelName : $data->channelId;
            throw new BadRequest(
                sprintf($this->getInjection('language')->translate('noSuchChannelInProduct'), $attributeName, $channelName, $product->get('name'))
            );
        }

        $this->setInputValue($entity, $data);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        $this->prepareInputValue($data);

        if ($this->isPseudoTransaction()) {
            return parent::updateEntity($id, $data);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::updateEntity($id, $data);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::updateEntity($id, $data);
        }

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

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($id);

        $pav1 = $this->getRepository()->get($id);
        foreach ($children as $child) {
            $pav2 = $this->getRepository()->get($child['id']);

            $inputData = new \stdClass();
            if ($this->getRepository()->arePavsValuesEqual($pav1, $pav2)) {
                foreach (['value', 'valueUnit', 'valueCurrency', 'valueId'] as $key) {
                    if (property_exists($data, $key)) {
                        $inputData->$key = $data->$key;
                    }
                }
            }
            if (Entity::areValuesEqual(Entity::BOOL, $pav1->get('isRequired'), $pav2->get('isRequired')) && property_exists($data, 'isRequired')) {
                $inputData->isRequired = $data->isRequired;
            }

            if (!empty((array)$inputData)) {
                if (in_array($pav1->get('attributeType'), ['multiEnum', 'array']) && property_exists($inputData, 'value') && is_string($inputData->value)) {
                    $inputData->value = @json_decode($inputData->value, true);
                }
                $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $child['id'], $inputData, $parentTransactionId);
                if ($child['childrenCount'] > 0) {
                    $this->createPseudoTransactionUpdateJobs($child['id'], clone $inputData, $transactionId);
                }
            }
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $this->setInputValue($entity, $data);
    }

    public function deleteEntity($id)
    {
        if (!empty($this->simpleRemove)) {
            return parent::deleteEntity($id);
        }

        if ($this->isPseudoTransaction()) {
            return parent::deleteEntity($id);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::deleteEntity($id);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::deleteEntity($id);
        }

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

    protected function createPseudoTransactionDeleteJobs(string $id, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($id);
        foreach ($children as $child) {
            $transactionId = $this->getPseudoTransactionManager()->pushDeleteEntityJob($this->entityType, $child['id'], $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionDeleteJobs($child['id'], $transactionId);
            }
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function setInputValue(Entity $entity, \stdClass $data): void
    {
        if (property_exists($data, 'value')) {
            // set attribute type if it needs
            if (empty($entity->get('attributeType')) && !empty($entity->get('attributeId'))) {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
                if (!empty($attribute)) {
                    $entity->set('attributeType', $attribute->get('type'));
                }
            }

            if (empty($entity->get('attributeType'))) {
                throw new BadRequest('No such attribute.');
            }

            switch ($entity->get('attributeType')) {
                case 'array':
                case 'multiEnum':
                case 'text':
                case 'wysiwyg':
                    $entity->set('textValue', $data->value);
                    break;
                case 'bool':
                    $entity->set('boolValue', $data->value);
                    break;
                case 'currency':
                    $entity->set('floatValue', $data->value);
                    if (property_exists($data, 'valueCurrency')) {
                        $entity->set('varcharValue', $data->valueCurrency);
                    }
                    break;
                case 'unit':
                    $entity->set('floatValue', $data->value);
                    if (property_exists($data, 'valueUnit')) {
                        $entity->set('varcharValue', $data->valueUnit);
                    }
                    break;
                case 'int':
                    $entity->set('intValue', $data->value);
                    break;
                case 'float':
                    $entity->set('floatValue', $data->value);
                    break;
                case 'date':
                    $entity->set('dateValue', $data->value);
                    break;
                case 'datetime':
                    $entity->set('datetimeValue', $data->value);
                    break;
                case 'asset':
                    $entity->set('varcharValue', $data->value);
                    break;
                default:
                    $entity->set('varcharValue', $data->value);
                    break;
            }
            $this->getRepository()->convertValue($entity);
        }
    }

    public function removeByTabAllNotInheritedAttributes(string $productId, string $tabId): bool
    {
        // check acl
        if (!$this->getAcl()->check('ProductAttributeValue', 'remove')) {
            throw new Forbidden();
        }

        $attributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->where([
                'attributeTabId' => empty($tabId) ? null : $tabId
            ])
            ->find();

        /** @var EntityCollection $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(
                [
                    'productId'   => $productId,
                    'attributeId' => array_column($attributes->toArray(), 'id')
                ]
            )
            ->find();

        foreach ($pavs as $pav) {
            if ($this->getAcl()->check($pav, 'remove')) {
                try {
                    $this->getEntityManager()->removeEntity($pav);
                } catch (BadRequest $e) {
                    // skip validation errors
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param array  $defs
     */
    protected function validateFieldWithPattern(Entity $entity, string $field, array $defs): void
    {
        if ($field == 'value' || ((!empty($multilangField = $defs['multilangField']) && $multilangField == 'value'))) {
            $attribute = !empty($entity->get('attribute')) ? $entity->get('attribute') : $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            $typesWithPattern = ['varchar'];

            if (in_array($attribute->get('type'), $typesWithPattern)
                && !empty($pattern = $attribute->get('pattern'))
                && !preg_match($pattern, $entity->get($field))) {
                $message = $this->getInjection('language')->translate('attributeDontMatchToPattern', 'exceptions', $entity->getEntityType());
                $message = str_replace('{attribute}', $attribute->get('name'), $message);
                $message = str_replace('{pattern}', $pattern, $message);

                throw new BadRequest($message);
            }
        } else {
            parent::validateFieldWithPattern($entity, $field, $defs);
        }
    }

    /**
     * @return array
     */
    protected function getInputLanguageList(): array
    {
        // prepare result
        $result = [];

        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                $result[$locale] = Util::toCamelCase('value_' . strtolower($locale));
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getRequiredFields(Entity $entity, \stdClass $data): array
    {
        $fields = parent::getRequiredFields($entity, $data);

        $values = ['value'];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $values[] = Util::toCamelCase('value_' . strtolower($locale));
        }

        $newFields = [];
        foreach ($fields as $field) {
            if (!in_array($field, $values)) {
                $newFields[] = $field;
            }
        }
        $fields = $newFields;

        return $fields;
    }

    /**
     * @inheritDoc
     */
    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        $this->prepareEntity($entity);

        if (in_array($entity->get('attributeType'), ['enum', 'multiEnum', 'unit'])) {
            return [];
        }

        $fields = parent::getFieldsThatConflict($entity, $data);

        if (!empty($fields) && property_exists($data, 'isProductUpdate') && !empty($data->isProductUpdate)) {
            $fields = [$entity->get('id') => $entity->get('attributeName')];
        }

        return $fields;
    }

    protected function getUserLanguage(): ?string
    {
        if ($this->userLanguage === null && !empty($localeId = $this->getLocaleId())) {
            $locale = $this->getEntityManager()->getRepository('Locale')->get($localeId);
            if (!empty($locale)) {
                $this->userLanguage = $locale->get('language');
            }
        }

        return $this->userLanguage;
    }

    protected function prepareEntity(Entity $entity): void
    {
        // exit if already prepared
        if (!empty($entity->get('attributeCode'))) {
            return;
        }

        if (empty($attribute = $entity->get('attribute'))) {
            throw new NotFound();
        }

        if (!empty($userLanguage = $this->getUserLanguage())) {
            $nameField = Util::toCamelCase('name_' . strtolower($userLanguage));
            if ($attribute->has($nameField)) {
                $entity->set('attributeName', $attribute->get($nameField));
            }
        }

        $lang = '';
        if ($entity->get('language') !== 'main') {
            $entity->set('attributeName', $entity->get('attributeName') . ' › ' . $entity->get('language'));
            $lang = ucfirst(Util::toCamelCase(strtolower($entity->get('language'))));
        }

        $entity->set('typeValue', $attribute->get("typeValue$lang"));
        $entity->set('attributeAssetType', $attribute->get('assetType'));
        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
        $entity->set('attributeCode', $attribute->get('code'));
        $entity->set('prohibitedEmptyValue', $attribute->get('prohibitedEmptyValue'));
        $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
        $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
        $entity->set('sortOrder', $attribute->get('sortOrder'));
        $entity->set('channelCode', null);
        if (!empty($channel = $entity->get('channel'))) {
            $entity->set('channelCode', $channel->get('code'));
        }

        $entity->set('isPavRelationInherited', $this->getRepository()->isPavRelationInherited($entity));
        if (!$entity->get('isPavRelationInherited')) {
            $entity->set('isPavRelationInherited', $this->getRepository()->isInheritedFromPf($entity));
        }

        if ($entity->get('isPavRelationInherited')) {
            $entity->set('isPavValueInherited', $this->getRepository()->isPavValueInherited($entity));
        }

        $this->getRepository()->convertValue($entity);

        $entity->clear('boolValue');
        $entity->clear('dateValue');
        $entity->clear('datetimeValue');
        $entity->clear('intValue');
        $entity->clear('floatValue');
        $entity->clear('varcharValue');
        $entity->clear('textValue');
    }

    private function prepareInputValue(\stdClass $data): void
    {
        if (property_exists($data, 'valueId') && !empty($data->valueId)) {
            $data->value = $data->valueId;
        }

        if (property_exists($data, 'value') && is_array($data->value)) {
            $data->value = Json::encode($data->value);
        }

        if (property_exists($data, 'data')) {
            if (property_exists($data->data, 'currency') && !property_exists($data, 'valueCurrency')) {
                $data->valueCurrency = $data->data->currency;
            }
            if (property_exists($data->data, 'unit') && !property_exists($data, 'valueCurrency')) {
                $data->valueUnit = $data->data->unit;
            }
        }
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $entity = $this->getRepository()->get($entity->get('id'));

        $this->prepareEntity($entity);

        return parent::isEntityUpdated($entity, $data);
    }

    protected function areValuesEqual(Entity $entity, string $field, $value1, $value2): bool
    {
        if (in_array($field, array_merge(['value'], array_values($this->getInputLanguageList())))) {
            $type = $entity->get('attributeType');
            $type = $this->getMetadata()->get(['fields', $type, 'fieldDefs', 'type'], $type);
        } else {
            $type = isset($entity->getFields()[$field]['type']) ? $entity->getFields()[$field]['type'] : 'varchar';
        }

        if (in_array($type, [Entity::JSON_ARRAY, Entity::JSON_OBJECT])) {
            if (is_string($value1)) {
                $value1 = Json::decode($value1, true);
            }
            if (is_string($value2)) {
                $value2 = Json::decode($value2, true);
            }
        }

        return Entity::areValuesEqual($type, $value1, $value2);
    }

    protected function getValueDataFields(): array
    {
        $fields = ['valueDataId'];

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $fields[] = 'valueData' . ucfirst(Util::toCamelCase(strtolower($language))) . 'Id';
            }
        }

        return $fields;
    }
}
