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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;

class ProductAttributeValue extends AbstractProductAttributeService
{
    protected $mandatorySelectAttributeList
        = [
            'maxLength',
            'language',
            'productId',
            'productName',
            'attributeId',
            'attributeName',
            'attributeType',
            'attributeTooltip',
            'intValue',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'varcharValue',
            'textValue'
        ];

    public function getGroupsPavs(string $productId, string $tabId, string $language = null): array
    {
        if (empty($productId)) {
            throw new NotFound();
        }

        if ($language === null) {
            $language = Language::detectLanguage($this->getConfig(), $this->getInjection('container')->get('preferences'));
        }

        $data = $this->getRepository()->getPavsWithAttributeGroupsData($productId, $tabId, $language);

        /**
         * Prepare attributes groups
         */
        $groups = [];
        foreach ($data as $record) {
            if (!empty($record['attribute_data']['attribute_group_id'])) {
                $groups[] = [
                    'id'        => $record['attribute_data']['attribute_group_id'],
                    'key'       => $record['attribute_data']['attribute_group_id'],
                    'label'     => $record['attribute_data']['attribute_group_name'],
                    'sortOrder' => $record['attribute_data']['attribute_group_sort_order']
                ];
            }
        }
        $groups['no_group'] = [
            'id'        => null,
            'key'       => 'no_group',
            'label'     => (new Language($this->getInjection('container'), $language))->translate('noGroup', 'labels', 'Product'),
            'sortOrder' => PHP_INT_MAX
        ];
        usort($groups, function ($a, $b) {
            if ($a['sortOrder'] == $b['sortOrder']) {
                return 0;
            }
            return ($a['sortOrder'] < $b['sortOrder']) ? -1 : 1;
        });
        foreach ($groups as $group) {
            unset($group['sortOrder']);
            $result[$group['key']] = $group;
            $result[$group['key']] = $group;

        }
        unset($groups);

        /**
         * Prepare attributes groups attributes
         */
        foreach ($data as $record) {
            $tooltip = null;
            if ($language === 'main') {
                $tooltip = $record['attribute_data']['tooltip'];
            } elseif (!empty($record['attribute_data']['tooltip_' . ucwords($language)])) {
                $tooltip = $record['attribute_data']['tooltip_' . ucwords($language)];
            }

            $row = [
                'id'          => $record['id'],
                'channelName' => $record['scope'] === 'Global' ? '-9999' : $record['channel_name'],
                'language'    => $record['language'] === 'main' ? null : $record['language'],
                'tooltip'     => $tooltip
            ];

            if (!isset($result[$record['attribute_data']['attribute_group_id']])) {
                $key = 'no_group';
                $row['sortOrder'] = empty($record['attribute_data']['sort_order_in_product']) ? 0 : (int)$record['attribute_data']['sort_order_in_product'];
            } else {
                $key = $record['attribute_data']['attribute_group_id'];
                $row['sortOrder'] = empty($record['attribute_data']['sort_order_in_attribute_group']) ? 0 : (int)$record['attribute_data']['sort_order_in_attribute_group'];
            }

            $result[$key]['pavs'][] = $row;
        }

        foreach ($result as $key => $group) {
            if (empty($group['pavs'])) {
                unset($result[$key]);
                continue 1;
            }
            $pavs = $group['pavs'];
            array_multisort(
                array_column($pavs, 'sortOrder'), SORT_ASC,
                array_column($pavs, 'channelName'), SORT_ASC,
                array_column($pavs, 'language'), SORT_ASC,
                $pavs
            );
            $result[$key]['rowList'] = array_column($pavs, 'id');
            unset($result[$key]['pavs']);
        }

        return array_values($result);
    }

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

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->getRepository()->loadAttributes(array_column($collection->toArray(), 'attributeId'));

        /**
         * Sort attribute values
         */
        $pavs = [];
        foreach ($collection as $k => $entity) {
            $row = [
                'entity'      => $entity,
                'channelName' => $entity->get('scope') === 'Global' ? '-9999' : $entity->get('channelName'),
                'language'    => $entity->get('language') === 'main' ? null : $entity->get('language')
            ];

            $attribute = $this->getRepository()->getPavAttribute($entity);

            if (!empty($attribute->get('attributeGroupId'))) {
                $row['sortOrder'] = empty($attribute->get('sortOrderInAttributeGroup')) ? 0 : (int)$attribute->get('sortOrderInAttributeGroup');
            } else {
                $row['sortOrder'] = empty($attribute->get('sortOrderInProduct')) ? 0 : (int)$attribute->get('sortOrderInProduct');
            }

            $pavs[$k] = $row;
        }

        array_multisort(
            array_column($pavs, 'sortOrder'), SORT_ASC,
            array_column($pavs, 'channelName'), SORT_ASC,
            array_column($pavs, 'language'), SORT_ASC,
            $pavs
        );

        foreach ($pavs as $k => $pav) {
            $collection->offsetSet($k, $pav['entity']);
        }

        parent::prepareCollectionForOutput($collection);
    }

    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        $this->prepareEntity($entity);

        parent::prepareEntityForOutput($entity);
    }

    /**
     * @inheritDoc
     */
    public function createEntity($attachment)
    {
        /**
         * For multiple creation via languages
         */
        $this->prepareDefaultLanguages($attachment);
        if (property_exists($attachment, 'languages') && !empty($attachment->languages)) {
            return $this->multipleCreateViaLanguages($attachment);
        }

        $this->prepareInputValue($attachment);
        $this->prepareDefaultValues($attachment);

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
            try {
                $this->createAssociatedAttributeValue($attachment, $attachment->attributeId);
            } catch (\Throwable $e) {
                // ignore errors
            }
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

    protected function createAssociatedAttributeValue(\stdClass $attachment, string $attributeId): void
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
            $aData->productId = $attachment->productId;
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
        ) {
            $productChannels = $product->get('productChannels');
            if (!empty($productChannels) && count($productChannels) > 0 && !in_array($data->channelId, array_column($productChannels->toArray(), 'channelId'))) {
                $attribute = $this->getEntityManager()->getRepository('Attribute')->get($data->attributeId);
                $attributeCode = empty($attribute) ? $data->attributeId : $attribute->get('code');

                $channel = $this->getEntityManager()->getRepository('Channel')->get($data->channelId);
                $channelCode = empty($channel) ? $data->channelId : $channel->get('code');

                $message = $this->getInjection('language')->translate('noSuchChannelInProduct', 'exceptions', 'ProductAttributeValue');
                throw new BadRequest(sprintf($message, $attributeCode, $channelCode, $product->get('name')));
            }
        }

        $this->setInputValue($entity, $data);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        if (!property_exists($data, 'attributeId')) {
            $entity = $this->getRepository()->get($id);
            if (!empty($entity)) {
                $data->attributeId = $entity->get('attributeId');
            }
        }

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

    /**
     * @param string $attributeGroupId
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function unlinkAttributeGroupHierarchy(string $attributeGroupId, string $productId): bool
    {
        $attributes = $this
            ->getRepository()
            ->select(['id'])
            ->join('attribute')
            ->where([
                'attribute.attributeGroupId' => $attributeGroupId,
                'productId'                  => $productId
            ])
            ->find()
            ->toArray();

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                try {
                    $this->deleteEntity($attribute['id']);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('AttributeGroup hierarchical removing from Product failed: ' . $e->getMessage());
                }
            }
        }

        return true;
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
        $this->addDependency('container');
    }

    protected function setInputValue(Entity $entity, \stdClass $data): void
    {
        if (property_exists($data, 'data') && property_exists($data->data, 'currency')) {
            $entity->set('varcharValue', $data->data->currency);
        }
        if (property_exists($data, 'valueCurrency')) {
            $entity->set('varcharValue', $data->valueCurrency);
        }

        if (property_exists($data, 'data') && property_exists($data->data, 'unit')) {
            $entity->set('varcharValue', $data->data->unit);
        }
        if (property_exists($data, 'valueUnit')) {
            $entity->set('varcharValue', $data->valueUnit);
        }

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
                    $entity->set('boolValue', !empty($data->value));
                    break;
                case 'int':
                    $entity->set('intValue', $data->value);
                    break;
                case 'currency':
                case 'unit':
                case 'float':
                    $entity->set('floatValue', $data->value);
                    break;
                case 'date':
                    $entity->set('dateValue', $data->value);
                    break;
                case 'datetime':
                    $entity->set('datetimeValue', $data->value);
                    break;
                default:
                    $entity->set('varcharValue', $data->value);
                    break;
            }
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
        if ($field == 'value' || ((!empty($defs['multilangField']) && $defs['multilangField'] == 'value'))) {
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

        foreach (['id', 'unit', 'currency'] as $item) {
            if (isset($fields['value' . ucfirst($item)])) {
                unset($fields['value' . ucfirst($item)]);
            }
        }

        return $fields;
    }

    protected function prepareEntity(Entity $entity): void
    {
        $attribute = $this->getRepository()->getPavAttribute($entity);

        if (empty($attribute)) {
            throw new NotFound();
        }

        if (!empty($userLanguage = $this->getInjection('preferences')->get('language'))) {
            $nameField = Util::toCamelCase('name_' . strtolower($userLanguage));
            if ($attribute->has($nameField)) {
                $entity->set('attributeName', $attribute->get($nameField));
            }
        }

        if ($entity->get('language') !== 'main') {
            $entity->set('attributeName', $attribute->get('name') . ' / ' . $entity->get('language'));
        }

        $locale = $entity->get('language');
        $tooltipFieldName = $locale == 'main' ? 'tooltip' : Util::toCamelCase('tooltip_' . strtolower($locale));
        $entity->set('attributeTooltip', $attribute->get($tooltipFieldName));

        $entity->set('attributeAssetType', $attribute->get('assetType'));
        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
        $entity->set('attributeCode', $attribute->get('code'));
        $entity->set('prohibitedEmptyValue', $attribute->get('prohibitedEmptyValue'));
        $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
        $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
        if (!empty($attribute->get('attributeGroup'))) {
            $entity->set('sortOrder', $attribute->get('sortOrderInAttributeGroup'));
        } else {
            $entity->set('sortOrder', $attribute->get('sortOrderInProduct'));
        }

        $entity->set('channelCode', null);
        if (!empty($channel = $entity->get('channel'))) {
            $entity->set('channelCode', $channel->get('code'));
        }

        if ($entity->get('scope') === 'Global') {
            $entity->set('channelId', null);
            $entity->set('channelName', 'Global');
        }

        $entity->set('isPavRelationInherited', $this->getRepository()->isPavRelationInherited($entity));
        if (!$entity->get('isPavRelationInherited')) {
            $entity->set('isPavRelationInherited', $this->getRepository()->isInheritedFromPf($entity));
        }

        if ($entity->get('isPavRelationInherited')) {
            $entity->set('isPavValueInherited', $this->getRepository()->isPavValueInherited($entity));
        }

        $this->getRepository()->convertValue($entity);

        if (!empty($attribute->get('isMultilang')) && !empty($mainLanguagePav = $this->getRepository()->getMainLanguagePav($entity))) {
            $entity->set('mainLanguageId', $mainLanguagePav->get('id'));
        }

        switch ($entity->get('attributeType')) {
            case 'unit':
                $this->prepareUnitFieldValue($entity, 'value', $attribute->get('measure'));
                break;
            case 'enum':
                if ($entity->get('varcharValue') !== null && !empty($entity->get('typeValueIds'))) {
                    $key = array_search((string)$entity->get('varcharValue'), $entity->get('typeValueIds'));
                    if ($key !== false && !empty($entity->get('typeValue')) && array_key_exists($key, $entity->get('typeValue'))) {
                        $entity->set('value', $entity->get('typeValue')[$key]);
                    }
                }
                break;
            case 'multiEnum':
                if ($entity->get('textValue') !== null && !empty($entity->get('typeValueIds'))) {
                    $values = [];
                    $options = @json_decode((string)$entity->get('textValue'), true);
                    if (!empty($options)) {
                        foreach ($options as $option) {
                            $key = array_search($option, $entity->get('typeValueIds'));
                            if ($key !== false && !empty($entity->get('typeValue')) && array_key_exists($key, $entity->get('typeValue'))) {
                                $values[] = $entity->get('typeValue')[$key];
                            }
                        }
                    }
                    $entity->set('value', $values);
                }
                break;
        }

        $entity->clear('boolValue');
        $entity->clear('dateValue');
        $entity->clear('datetimeValue');
        $entity->clear('intValue');
        $entity->clear('floatValue');
        $entity->clear('varcharValue');
        $entity->clear('textValue');
    }

    private function prepareInputValue($data): void
    {
        if (!is_object($data)) {
            return;
        }

        if (property_exists($data, 'attributeId')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($data->attributeId);
        }

        if (property_exists($data, 'valueId') && !empty($data->valueId)) {
            $data->value = $data->valueId;
        }

        if (property_exists($data, 'value') && is_array($data->value)) {
            $data->value = json_encode($data->value);
        }

        /**
         * Prepare via attribute type
         */
        if (property_exists($data, 'value') && !empty($attribute)) {
            switch ($attribute->get('type')) {
                case 'enum':
                    $key = array_search((string)$data->value, $attribute->get('typeValue'));
                    if ($key !== false && !empty($attribute->get('typeValueIds')) && array_key_exists($key, $attribute->get('typeValueIds'))) {
                        $data->value = $attribute->get('typeValueIds')[$key];
                    }
                    break;
                case 'multiEnum':
                    $values = [];
                    $options = @json_decode((string)$data->value, true);
                    if (!empty($options)) {
                        foreach ($options as $option) {
                            $key = array_search($option, $attribute->get('typeValue'));
                            if ($key !== false && !empty($attribute->get('typeValueIds')) && array_key_exists($key, $attribute->get('typeValueIds'))) {
                                $values[] = $attribute->get('typeValueIds')[$key];
                            }
                        }
                    }
                    $data->value = json_encode($values);
                    break;
            }
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
                    $productChannels = $this
                        ->getEntityManager()
                        ->getRepository('ProductChannel')
                        ->select(['channelId'])
                        ->where(['productId' => $data->productId])
                        ->find()
                        ->toArray();

                    if (in_array($attribute->get('defaultChannelId'), array_column($productChannels, 'channelId'))) {
                        $data->scope = $defaultScope;
                        $data->channelId = $attribute->get('defaultChannelId');
                        $data->channelName = $attribute->get('defaultChannelName');
                    } else {
                        $data->scope = 'Global';
                    }
                }
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
