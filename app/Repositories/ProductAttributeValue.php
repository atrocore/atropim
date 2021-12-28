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
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Espo\Core\Utils\Util;

class ProductAttributeValue extends AbstractRepository
{
    protected static $beforeSaveData = [];

    public function convertValue(Entity $entity): void
    {
        if (empty($entity->get('attributeType'))) {
            return;
        }

        switch ($entity->get('attributeType')) {
            case 'array':
            case 'multiEnum':
                $entity->set('value', @json_decode((string)$entity->get('textValue'), true));
                break;
            case 'text':
            case 'wysiwyg':
                $entity->set('value', $entity->get('textValue'));
                break;
            case 'bool':
                $entity->set('value', $entity->get('boolValue'));
                break;
            case 'currency':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueCurrency', $entity->get('varcharValue'));
                break;
            case 'unit':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueUnit', $entity->get('varcharValue'));
                break;
            case 'int':
                $entity->set('value', $entity->get('intValue'));
                break;
            case 'float':
                $entity->set('value', $entity->get('floatValue'));
                break;
            case 'date':
                $entity->set('value', $entity->get('dateValue'));
                break;
            case 'datetime':
                $entity->set('value', $entity->get('datetimeValue'));
                break;
            case 'asset':
                $entity->set('value', $entity->get('varcharValue'));
                $entity->set('valueId', $entity->get('varcharValue'));
                if (!empty($entity->get('valueId'))) {
                    if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get('valueId')))) {
                        $entity->set('valueName', $attachment->get('name'));
                        $entity->set('valuePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment));
                    }
                }
                break;
            default:
                $entity->set('value', $entity->get('varcharValue'));
                break;
        }
    }

    public function get($id = null)
    {
        $entity = parent::get($id);
        $this->convertValue($entity);

        return $entity;
    }

    public function save(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
        }

        try {
            $result = parent::save($entity, $options);
            if ($result) {
                $this->updateIsRequiredForLanguages($entity);
            }

            if ($this->getPDO()->inTransaction()) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($this->getPDO()->inTransaction()) {
                $this->getPDO()->rollBack();
            }

            // if duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                $channelName = $entity->get('scope');
                if ($channelName == 'Channel') {
                    $channelName = !empty($entity->get('channelId')) ? "'" . $entity->get('channel')->get('name') . "'" : '';
                }
                throw new ProductAttributeAlreadyExists(sprintf($this->exception('productAttributeAlreadyExists'), $entity->get('attribute')->get('name'), $channelName));
            }

            throw $e;
        }

        return $result;
    }

    public function remove(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
        }

        $this->beforeRemove($entity, $options);

        try {
            $this->deleteFromDb($entity->get('id'));
            $this->removeLanguages($entity);
            if ($this->getPDO()->inTransaction()) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($this->getPDO()->inTransaction()) {
                $this->getPDO()->rollBack();
            }
            return false;
        }

        $this->afterRemove($entity, $options);

        return true;
    }

    public function findCopy(Entity $entity): ?Entity
    {
        $where = [
            'id!='        => $entity->get('id'),
            'language'    => $entity->get('language'),
            'productId'   => $entity->get('productId'),
            'attributeId' => $entity->get('attributeId'),
            'scope'       => $entity->get('scope'),
        ];
        if ($entity->get('scope') == 'Channel') {
            $where['channelId'] = $entity->get('channelId');
        }

        return $this->where($where)->findOne();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew()) {
            self::$beforeSaveData = $this->getEntityManager()->getEntity('ProductAttributeValue', $entity->get('id'))->toArray();
        }

        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        if (empty($entity->get('language'))) {
            $entity->set('language', '');
        }

        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        if ($entity->isNew()) {
            $entity->set('attributeType', $attribute->get('type'));
            if ($attribute->get('type') === 'enum' && !empty($attribute->get('enumDefault'))) {
                $entity->set('varcharValue', $attribute->get('enumDefault'));
            }

            if ($attribute->get('type') === 'unit') {
                $entity->set('floatValue', $attribute->get('unitDefault'));
                $entity->set('varcharValue', $attribute->get('unitDefaultUnit'));
            }

            if (!empty($attribute->get('isMultilang'))) {
                $this->getEntityManager()->getRepository('Product')->updateProductsAttributesViaProductIds([$entity->get('productId')]);
            }
        }

        $this->syncEnumValues($entity);

        $this->syncMultiEnumValues($entity);

        if ($entity->isNew() && !$this->getMetadata()->isModuleInstalled('OwnershipInheritance')) {
            $product = $entity->get('product');

            if (empty($entity->get('assignedUserId'))) {
                $entity->set('assignedUserId', $product->get('assignedUserId'));
            }

            if (empty($entity->get('ownerUserId'))) {
                $entity->set('ownerUserId', $product->get('ownerUserId'));
            }

            if (empty($entity->get('teamsIds'))) {
                $entity->set('teamsIds', array_column($product->get('teams')->toArray(), 'id'));
            }
        }

        if (!$entity->isNew() && $attribute->get('unique') && $entity->isAttributeChanged('value')) {
            $where = [
                'id!='            => $entity->id,
                'boolValue'       => $entity->get('boolValue'),
                'dateValue'       => $entity->get('dateValue'),
                'datetimeValue'   => $entity->get('datetimeValue'),
                'intValue'        => $entity->get('intValue'),
                'floatValue'      => $entity->get('floatValue'),
                'varcharValue'    => $entity->get('varcharValue'),
                'textValue'       => $entity->get('textValue'),
                'language'        => $entity->get('language'),
                'attributeId'     => $entity->get('attributeId'),
                'product.deleted' => false
            ];

            if (!empty($this->select(['id'])->join(['product'])->where($where)->findOne())) {
                throw new BadRequest(sprintf($this->exception("attributeShouldHaveBeUnique"), $entity->get('attribute')->get('name')));
            }
        }

        // exit
        if (!empty($options['skipProductAttributeValueHook'])) {
            return;
        }

        // create note
        if (!$entity->isNew() && $entity->isAttributeChanged('value')) {
            $this->createNote($entity);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'assignedUser', 'isInheritAssignedUser'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('assignedUserAttributeOwnership', null));
        }

        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'ownerUser', 'isInheritOwnerUser'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('ownerUserAttributeOwnership', null));
        }

        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'teams', 'isInheritTeams'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('teamsAttributeOwnership', null));
        }

        // update modifiedAt for product
        $this
            ->getEntityManager()
            ->nativeQuery("UPDATE `product` SET modified_at='{$entity->get('modifiedAt')}' WHERE id='{$entity->get('productId')}'");

        $this->moveImageFromTmp($entity);

        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param string $param
     *
     * @return string|null
     */
    protected function getPreparedInheritedField(Entity $entity, string $field, string $param): ?string
    {
        if ($entity->isAttributeChanged($param) && $entity->get($param)) {
            return $field;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function getInheritedEntity(Entity $entity, string $config): ?Entity
    {
        $result = null;

        if ($config == 'fromAttribute') {
            $result = $entity->get('attribute');
        } elseif ($config == 'fromProduct') {
            $result = $entity->get('product');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        return empty($this->findCopy($entity));
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ProductAttributeValue');
    }

    protected function syncEnumValues(Entity $entity): void
    {
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return;
        }

        // get attribute
        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        if ($attribute->get('type') !== 'enum' || empty($attribute->get('isMultilang'))) {
            return;
        }

        // @todo

        echo '<pre>';
        print_r('syncEnumValues');
        die();

        $locale = '';
        if (!empty($entity->get('isLocale'))) {
            $locale = ucfirst(Util::toCamelCase(strtolower($entity->get('locale'))));
        }

        if (!$entity->isAttributeChanged('value' . $locale)) {
            return;
        }

        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return;
        }

        $key = array_search($entity->get('value' . $locale), $this->prepareTypeValue($attribute, $locale));

        if ($key === false) {
            return;
        }

        $locales = [''];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
            $locales[] = ucfirst(Util::toCamelCase(strtolower($v)));
        }

        foreach ($locales as $locale) {
            $typeValue = $this->prepareTypeValue($attribute, $locale);
            $entity->set('value' . $locale, $typeValue[$key]);
        }
    }

    /**
     * @param \Pim\Entities\Attribute $attribute
     * @param string                  $locale
     *
     * @return array|null
     */
    protected function prepareTypeValue(\Pim\Entities\Attribute $attribute, string $locale): ?array
    {
        $result = null;

        if ($attribute->get('type') == 'enum') {
            $result = $attribute->get('typeValue' . $locale);

            if (!$attribute->get('prohibitedEmptyValue')) {
                array_unshift($result, '');
            }
        }

        return $result;
    }

    protected function syncMultiEnumValues(Entity $entity): void
    {
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return;
        }

        // get attribute
        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        if ($attribute->get('type') !== 'multiEnum' || empty($attribute->get('isMultilang'))) {
            return;
        }

        // @todo

        echo '<pre>';
        print_r('syncMultiEnumValues');
        die();

        $locale = '';
        if (!empty($entity->get('isLocale'))) {
            $locale = ucfirst(Util::toCamelCase(strtolower($entity->get('locale'))));
        }

        if (!$entity->isAttributeChanged('value' . $locale)) {
            return;
        }

        $values = Json::decode($entity->get('value' . $locale), true);

        $keys = [];
        foreach ($values as $value) {
            $keys[] = array_search($value, $attribute->get('typeValue' . $locale));
        }

        $locales = [''];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
            $locales[] = ucfirst(Util::toCamelCase(strtolower($v)));
        }

        foreach ($locales as $locale) {
            $typeValue = $attribute->get('typeValue' . $locale);

            $values = [];
            foreach ($keys as $key) {
                if ($key !== false) {
                    $values[] = isset($typeValue[$key]) ? $typeValue[$key] : null;
                }
            }
            $entity->set('value' . $locale, Json::encode($values));
        }
    }

    protected function createOwnNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function createAssignmentNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function moveImageFromTmp(Entity $attributeValue): void
    {
        if (!empty($attribute = $attributeValue->get('attribute')) && $attribute->get('type') === 'image' && !empty($attributeValue->get('value'))) {
            $file = $this->getEntityManager()->getEntity('Attachment', $attributeValue->get('value'));
            $this->getInjection('serviceFactory')->create($file->getEntityType())->moveFromTmp($file);
            $this->getEntityManager()->saveEntity($file);
        }
    }

    protected function createNote(Entity $entity, string $locale = '')
    {
        if (!empty($data = $this->getNoteData($entity, $locale))) {
            $note = $this->getEntityManager()->getEntity('Note');
            $note->set('type', 'Update');
            $note->set('parentId', $entity->get('productId'));
            $note->set('parentType', 'Product');
            $note->set('data', $data);
            $note->set('attributeId', $entity->get('id'));

            $this->getEntityManager()->saveEntity($note);
        }
    }

    protected function getNoteData(Entity $entity, string $locale = ''): array
    {
        // get attribute
        $attribute = $entity->get('attribute');

        // prepare field name
        $fieldName = $this->getInjection('language')->translate('Attribute', 'custom', 'ProductAttributeValue') . ' ' . $attribute->get('name');

        // prepare result
        $result = [];

        // prepare field name
        if ($locale) {
            $field = Util::toCamelCase('value_' . strtolower($locale));
            $fieldName .= " ($locale)";
        } else {
            $field = 'value';
        }

        if (
            self::$beforeSaveData[$field] != $entity->get($field)
            || ($entity->isAttributeChanged('data') && !empty(array_diff((array)self::$beforeSaveData['data'], (array)$entity->get('data'))))
        ) {
            $result['fields'][] = $fieldName;
            $result['locale'] = $locale;
            $type = $attribute->get('type');

            $result['attributes']['was'][$fieldName] = $this->convertAttributeValue($type, self::$beforeSaveData[$field]);
            $result['attributes']['became'][$fieldName] = $this->convertAttributeValue($type, $entity->get($field));

            if ($entity->get('attribute')->get('type') == 'unit') {
                $result['attributes']['was'][$fieldName . 'Unit'] = self::$beforeSaveData['data']->unit;
                $result['attributes']['became'][$fieldName . 'Unit'] = $entity->get('data')->unit;
            } elseif ($entity->get('attribute')->get('type') == 'currency') {
                $result['attributes']['was'][$fieldName . 'Currency'] = self::$beforeSaveData['data']->currency;
                $result['attributes']['became'][$fieldName . 'Currency'] = $entity->get('data')->currency;
            }
        }

        return $result;
    }

    protected function convertAttributeValue(string $type, $value)
    {
        $result = null;

        switch ($type) {
            case 'array':
            case 'multiEnum':
                $result = Json::decode($value, true);
                break;
            case 'bool':
                $result = (bool)$value;
                break;
            default:
                if (!empty($value)) {
                    $result = $value;
                }
        }

        return $result;
    }

    protected function validateFieldsByType(Entity $entity): void
    {
        parent::validateFieldsByType($entity);

        $this->validateEnumAttribute($entity);
        $this->validateUnitAttribute($entity);
    }


    protected function validateEnumAttribute(Entity $entity): void
    {
        if (empty($attribute = $entity->get('attribute'))) {
            return;
        }

        $type = $attribute->get('type');

        if (!in_array($type, ['enum', 'multiEnum'])) {
            return;
        }

        $fieldNames = ['value'];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $fieldNames[] = 'value' . ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        foreach ($fieldNames as $fieldName) {
            if ($entity->isAttributeChanged($fieldName) && !empty($entity->get($fieldName))) {
                $optionsField = 'type' . ucfirst($fieldName);
                $fieldOptions = empty($attribute->get($optionsField)) ? [] : $attribute->get($optionsField);

                if (empty($fieldOptions) && $type === 'multiEnum') {
                    continue 1;
                }

                $value = $entity->get($fieldName);
                if ($type == 'enum') {
                    $value = [$value];
                }
                if ($type == 'multiEnum') {
                    $value = Json::decode($value, true);
                }

                foreach ($value as $v) {
                    if (!in_array($v, $fieldOptions)) {
                        throw new BadRequest(
                            sprintf(
                                $this->getInjection('container')->get('language')->translate('noSuchAttributeOptions', 'exceptions', 'ProductAttributeValue'),
                                $attribute->get('name')
                            )
                        );
                    }
                }
            }
        }
    }

    protected function validateUnitAttribute(Entity $entity): void
    {
        if (empty($attribute = $entity->get('attribute'))) {
            return;
        }

        $type = $attribute->get('type');

        if ($type !== 'unit') {
            return;
        }

        $language = $this->getInjection('container')->get('language');

        $unitsOfMeasure = $this->getConfig()->get('unitsOfMeasure');
        $unitsOfMeasure = empty($unitsOfMeasure) ? [] : Json::decode(Json::encode($unitsOfMeasure), true);

        $value = $entity->get('value');
        $unit = $entity->get('valueUnit');

        $label = $attribute->get('name');

        if ($value !== null && $value !== '' && empty($unit)) {
            throw new BadRequest(sprintf($language->translate('attributeUnitValueIsRequired', 'exceptions', 'ProductAttributeValue'), $label));
        }

        $measure = empty($attribute->get('typeValue') || !is_array($attribute->get('typeValue'))) ? '' : $attribute->get('typeValue')[0];

        if (!empty($unit)) {
            $units = empty($unitsOfMeasure[$measure]['unitList']) ? [] : $unitsOfMeasure[$measure]['unitList'];
            if (!in_array($unit, $units)) {
                throw new BadRequest(sprintf($language->translate('noSuchAttributeUnit', 'exceptions', 'ProductAttributeValue'), $label));
            }
        }
    }

    protected function updateIsRequiredForLanguages(Entity $entity): void
    {
        if ($entity->has('isRequired')) {
            $isRequired = empty($entity->get('isRequired')) ? 0 : 1;
            $queries[] = "UPDATE `product_attribute_value` SET is_required=$isRequired WHERE main_language_id='{$entity->get('id')}'";
            if (!empty($entity->get('mainLanguageId'))) {
                $queries[] = "UPDATE `product_attribute_value` SET is_required=$isRequired WHERE id='{$entity->get('mainLanguageId')}'";
                $queries[] = "UPDATE `product_attribute_value` SET is_required=$isRequired WHERE main_language_id='{$entity->get('mainLanguageId')}'";
            }
            $this->getPDO()->exec(implode(";", $queries));
        }
    }

    protected function removeLanguages(Entity $entity): void
    {
        $queries[] = "DELETE FROM `product_attribute_value` WHERE main_language_id='{$entity->get('id')}'";
        if (!empty($entity->get('mainLanguageId'))) {
            $queries[] = "DELETE FROM `product_attribute_value` WHERE id='{$entity->get('mainLanguageId')}'";
            $queries[] = "DELETE FROM `product_attribute_value` WHERE main_language_id='{$entity->get('mainLanguageId')}'";
        }
        $this->getPDO()->exec(implode(";", $queries));
    }
}
