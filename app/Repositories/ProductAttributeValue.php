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

/**
 * Class ProductAttributeValue
 */
class ProductAttributeValue extends AbstractRepository
{
    protected static $beforeSaveData = [];

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        $this->isValidForSave($entity, $options);

        if (!$entity->isNew()) {
            self::$beforeSaveData = $this->getEntityManager()->getEntity('ProductAttributeValue', $entity->get('id'))->toArray();
        }

        if ($entity->get('scope') == 'Global') {
            $entity->set('channelId', null);
        }

        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
        if ($entity->isNew() && $attribute->get('type') === 'enum' && empty($entity->get('value')) && !empty($attribute->get('enumDefault'))) {
            $entity->set('value', $attribute->get('enumDefault'));
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

        if (!$entity->isNew() && $attribute->get('unique')) {
            $valueChanged = null;

            // check if value field changed
            if ($entity->isAttributeChanged('value') && !empty($entity->get('value'))) {
                $valueChanged = 'value';
            } else {
                if ($entity->get('attribute')->get('isMultilang') && $this->getConfig()->get('isMultilangActive', false)) {
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                        $localeField = 'value' . ucfirst(Util::toCamelCase(strtolower($locale)));

                        if (!empty($entity->get($localeField)) && $entity->isAttributeChanged($localeField)) {
                            $valueChanged = $localeField;
                        }
                    }
                }
            }

            $where = [
                'id!='            => $entity->id,
                'attributeId'     => $entity->get('attributeId'),
                'product.deleted' => false
            ];

            if (!empty($valueChanged)) {
                $where[$valueChanged] = $entity->get($valueChanged);
                $where['data'] = !empty($entity->get('data')) ? Json::encode($entity->get('data')) : null;
            } elseif ($entity->isAttributeChanged('data') && !empty($entity->get('data'))) {
                // if only data field changed (for unit or currency attributes)
                $where['value'] = $entity->get('value');
                $where['data'] = Json::encode($entity->get('data'));
            } else {
                return;
            }

            $count = $this
                ->getEntityManager()
                ->getRepository($entity->getEntityType())
                ->join(['product'])
                ->where($where)
                ->count();

            if ($count) {
                $message = sprintf($this->exception("attributeShouldHaveBeUnique"), $entity->get('attribute')->get('name'));
                throw new BadRequest($message);
            }
        }

        // exit
        if (!empty($options['skipProductAttributeValueHook'])) {
            return;
        }

        // create note
        if (!$entity->isNew() && ($entity->isAttributeChanged('value') || $entity->isAttributeChanged('data'))) {
            $this->createNote($entity);
        }

        if (!in_array($attribute->get('type'), ['enum', 'multiEnum'])) {
            $langList = $this->getConfig()->get('inputLanguageList', []);
            foreach ($langList as $locale) {
                $field = Util::toCamelCase('value_' . strtolower($locale));

                if (!$entity->isNew() && $entity->isAttributeChanged($field)) {
                    $this->createNote($entity, $locale);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    public function afterSave(Entity $entity, array $options = array())
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
     * @param string $id
     * @param string $locale
     *
     * @return array
     */
    public function getMultilangAttributeId(string $id, string $locale): array
    {
        $separator = \Pim\Services\ProductAttributeValue::LOCALE_IN_ID_SEPARATOR;

        $sql = "SELECT CONCAT(pav.attribute_id, '{$separator}', '{$locale}') AS id
                FROM product_attribute_value pav
                WHERE pav.id = '{$id}'";

        return $this->getEntityManager()->nativeQuery($sql)->fetch(\PDO::FETCH_ASSOC);
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

        if ($this->getConfig()->get('isMultilangActive', false) && $entity->get('isLocale')) {

            if (isset($entity->locale)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    if ($locale == $entity->locale) {
                        $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                        $param .= $camelCaseLocale;

                        if ($entity->isAttributeChanged($param) && $entity->get($param)) {
                            return $field;
                        }
                    }
                }
            }
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
     * @param Entity $entity
     *
     * @return Entity|null
     */
    public function findCopy(Entity $entity): ?Entity
    {
        $where = [
            'id!='        => $entity->get('id'),
            'productId'   => $entity->get('productId'),
            'attributeId' => $entity->get('attributeId'),
            'scope'       => $entity->get('scope'),
        ];
        if ($entity->get('scope') == 'Channel') {
            $where['channelId'] = $entity->get('channelId');
        }

        return $this->where($where)->findOne();
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

    protected function isValidForSave(Entity $entity, array $options): bool
    {
        // exit
        if (!empty($options['skipProductAttributeValueHook'])) {
            return true;
        }

        $product = $this->getEntityManager()->getEntity('Product', $entity->get('productId'));
        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        /**
         * Validation. Product and Attribute can't by empty
         */
        if (empty($product) || empty($attribute)) {
            throw new BadRequest($this->exception('Product and Attribute cannot be empty'));
        }

        /**
         * Validation. Is such ProductAttribute exist?
         */
        if (!$this->isUnique($entity)) {
            $channelName = $entity->get('scope');
            if ($channelName == 'Channel') {
                $channelName = !empty($entity->get('channelId')) ? "'" . $entity->get('channel')->get('name') . "'" : '';
            }

            throw new ProductAttributeAlreadyExists(sprintf($this->exception('productAttributeAlreadyExists'), $entity->get('attribute')->get('name'), $channelName));
        }

        return true;
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

        $key = array_search($entity->get('value' . $locale), $attribute->get('typeValue' . $locale));

        if ($key === false) {
            return;
        }

        $locales = [''];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
            $locales[] = ucfirst(Util::toCamelCase(strtolower($v)));
        }

        foreach ($locales as $locale) {
            $typeValue = $attribute->get('typeValue' . $locale);
            $entity->set('value' . $locale, $typeValue[$key]);
        }
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

    protected function validateEnum(Entity $entity): void
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
                        throw new BadRequest(sprintf($this->getInjection('container')->get('language')->translate('noSuchAttributeOptions', 'exceptions', 'ProductAttributeValue'), $attribute->get('name')));
                    }
                }
            }
        }
    }
}
