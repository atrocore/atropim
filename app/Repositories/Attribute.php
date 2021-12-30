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
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

/**
 * Class Attribute
 */
class Attribute extends AbstractRepository
{
    /**
     * @var string
     */
    protected $ownership = 'fromAttribute';

    /**
     * @var string
     */
    protected $ownershipRelation = 'ProductAttributeValue';

    /**
     * @var string
     */
    protected $assignedUserOwnership = 'assignedUserAttributeOwnership';

    /**
     * @var string
     */
    protected $ownerUserOwnership = 'ownerUserAttributeOwnership';

    /**
     * @var string
     */
    protected $teamsOwnership = 'teamsAttributeOwnership';

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if (!$this->isTypeValueValid($entity)) {
            throw new BadRequest("The number of 'Values' items should be identical for all locales");
        }

        if (!$entity->isNew()) {
            $this->updateEnumPav($entity);
            $this->updateMultiEnumPav($entity);
        }

        // set sort order
        if (is_null($entity->get('sortOrder'))) {
            $entity->set('sortOrder', (int)$this->max('sortOrder') + 1);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('sortOrder')) {
            $this->updateSortOrder($entity);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('unique') && $entity->get('unique')) {
            $languages = ['main'];
            if ($this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    $languages[] = $language;
                }
            }

            foreach ($languages as $language) {
                $query
                    = "SELECT COUNT(*) FROM product_attribute_value WHERE attribute_id='{$entity->id}' AND language='$language' AND deleted=0 %s GROUP BY %s HAVING COUNT(*) > 1";
                switch ($entity->get('type')) {
                    case 'unit':
                    case 'currency':
                        $query = sprintf($query, 'AND float_value IS NOT NULL AND varchar_value IS NOT NULL', 'float_value, varchar_value');
                        break;
                    case 'float':
                        $query = sprintf($query, 'AND float_value IS NOT NULL', 'float_value');
                        break;
                    case 'int':
                        $query = sprintf($query, 'AND int_value IS NOT NULL', 'int_value');
                    case 'date':
                        $query = sprintf($query, 'AND date_value IS NOT NULL', 'date_value');
                    case 'datetime':
                        $query = sprintf($query, 'AND datetime_value IS NOT NULL', 'datetime_value');
                        break;
                    default:
                        $query = sprintf($query, 'AND varchar_value IS NOT NULL', 'varchar_value');
                        break;
                }

                if (!empty($this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC))) {
                    throw new Error($this->exception('attributeNotHaveUniqueValue'));
                }
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('pattern') && !empty($pattern = $entity->get('pattern')) && preg_match('/\^(.*)\$/', $pattern, $matches)) {
            $query = "SELECT id 
                      FROM product_attribute_value 
                      WHERE deleted=0 
                        AND attribute_type='varchar' 
                        AND varchar_value IS NOT NULL 
                        AND varchar_value!='' 
                        AND varchar_value NOT REGEXP '$matches[0]'";

            if (!empty($this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC))) {
                throw new BadRequest($this->exception('someAttributeDontMathToPattern'));
            }
        }

        // call parent action
        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isMultilang')) {
            $this
                ->getEntityManager()
                ->getRepository('Product')
                ->updateProductsAttributes("SELECT product_id FROM `product_attribute_value` WHERE attribute_id='{$entity->get('id')}' AND deleted=0", true);
        }

        $this->setInheritedOwnership($entity);
    }

    /**
     * @inheritDoc
     */
    public function max($field)
    {
        $data = $this
            ->getPDO()
            ->query("SELECT MAX(sort_order) AS max FROM attribute WHERE deleted=0")
            ->fetch(\PDO::FETCH_ASSOC);

        return $data['max'];
    }

    /**
     * @inheritdoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'products') {
            // prepare data
            $attributeId = (string)$entity->get('id');
            $productId = (is_string($foreign)) ? $foreign : (string)$foreign->get('id');

            if ($this->isProductFamilyAttribute($attributeId, $productId)) {
                throw new Error($this->exception("youCanNotUnlinkProductFamilyAttribute"));
            }
        }
    }

    protected function updateEnumPav(Entity $attribute): void
    {
        if ($attribute->get('type') != 'enum') {
            return;
        }

        if (!$this->isEnumTypeValueValid($attribute)) {
            return;
        }

        if (empty($attribute->getFetched('typeValueIds'))) {
            return;
        }

        // prepare became values
        $becameValues = [];
        foreach ($attribute->get('typeValueIds') as $k => $v) {
            foreach ($attribute->getFetched('typeValueIds') as $k1 => $v1) {
                if ($v1 === $v) {
                    $becameValues[$attribute->getFetched('typeValue')[$k1]] = $attribute->get('typeValue')[$k];
                }
            }
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['id', 'language', 'varcharValue'])
            ->where(['attributeId' => $attribute->get('id'), 'language' => 'main'])
            ->find()
            ->toArray();

        foreach ($pavs as $pav) {
            $queries = [];

            /**
             * First, prepare main value
             */
            if (!empty($becameValues[$pav['varcharValue']])) {
                $queries[] = "UPDATE product_attribute_value SET varchar_value='{$becameValues[$pav['varcharValue']]}' WHERE id='{$pav['id']}'";
            } else {
                $queries[] = "UPDATE product_attribute_value SET varchar_value=NULL WHERE id='{$pav['id']}'";
            }

            /**
             * Second, update locales
             */
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    if (!empty($becameValues[$pav['varcharValue']])) {
                        $options = $attribute->get("typeValue" . ucfirst(Util::toCamelCase(strtolower($language))));
                        $key = array_search($pav['varcharValue'], $attribute->getFetched('typeValue'));
                        $value = isset($options[$key]) ? $options[$key] : $becameValues[$pav['varcharValue']];
                        $queries[] = "UPDATE product_attribute_value SET varchar_value='$value' WHERE main_language_id='{$pav['id']}' AND language='$language'";
                    } else {
                        $queries[] = "UPDATE product_attribute_value SET varchar_value=NULL WHERE main_language_id='{$pav['id']}' AND language='$language'";
                    }
                }
            }

            /**
             * Third, set to DB
             */
            $this->getPDO()->exec(implode(';', $queries));
        }
    }

    protected function updateMultiEnumPav(Entity $attribute): void
    {
        if ($attribute->get('type') != 'multiEnum') {
            return;
        }

        if (!$this->isEnumTypeValueValid($attribute)) {
            return;
        }

        if (empty($attribute->getFetched('typeValueIds'))) {
            return;
        }

        // prepare became values
        $becameValues = [];
        foreach ($attribute->get('typeValueIds') as $k => $v) {
            foreach ($attribute->getFetched('typeValueIds') as $k1 => $v1) {
                if ($v1 === $v) {
                    $becameValues[$attribute->getFetched('typeValue')[$k1]] = $attribute->get('typeValue')[$k];
                }
            }
        }

        /** @var array $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['id', 'language', 'textValue'])
            ->where(['attributeId' => $attribute->get('id'), 'language' => 'main'])
            ->find()
            ->toArray();

        foreach ($pavs as $pav) {
            $queries = [];

            /**
             * First, prepare main value
             */
            $values = [];
            if (!empty($pav['textValue'])) {
                $jsonData = @json_decode($pav['textValue'], true);
                if (!empty($jsonData)) {
                    $values = $jsonData;
                }
            }

            if (!empty($values)) {
                $newValues = [];
                foreach ($values as $value) {
                    if (isset($becameValues[$value])) {
                        $newValues[] = $becameValues[$value];
                    }
                }
                $pav['textValue'] = Json::encode($newValues);
                $values = $newValues;
            }

            $queries[] = "UPDATE product_attribute_value SET text_value='{$pav['textValue']}' WHERE id='{$pav['id']}'";

            /**
             * Second, update locales
             */
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    $options = $attribute->get("typeValue" . ucfirst(Util::toCamelCase(strtolower($language))));
                    $localeValues = [];
                    foreach ($values as $value) {
                        $key = array_search($value, $attribute->get('typeValue'));
                        $localeValues[] = isset($options[$key]) ? $options[$key] : $value;
                    }
                    $localeValues = Json::encode($localeValues);
                    $queries[] = "UPDATE product_attribute_value SET text_value='$localeValues' WHERE main_language_id='{$pav['id']}' AND language='$language'";
                }
            }

            /**
             * Third, set to DB
             */
            $this->getPDO()->exec(implode(';', $queries));
        }
    }

    /**
     * @param $entity
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isEnumTypeValueValid($entity): bool
    {
        if (!empty($entity->get('typeValue'))) {
            foreach (array_count_values($entity->get('typeValue')) as $count) {
                if ($count > 1) {
                    throw new BadRequest($this->exception('attributeValueShouldBeUnique'));
                }
            }
        }

        return true;
    }

    /**
     * @param string $attributeId
     * @param string $productId
     *
     * @return bool
     */
    protected function isProductFamilyAttribute(string $attributeId, string $productId): bool
    {
        $value = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['id'])
            ->where(['attributeId' => $attributeId, 'productId' => $productId, 'productFamilyId !=' => null])
            ->findOne();

        return !empty($value);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, "exceptions", "Attribute");
    }

    /**
     * @param Entity $entity
     */
    protected function updateSortOrder(Entity $entity): void
    {
        $data = $this
            ->select(['id'])
            ->where(
                [
                    'id!='             => $entity->get('id'),
                    'sortOrder>='      => $entity->get('sortOrder'),
                    'attributeGroupId' => $entity->get('attributeGroupId')
                ]
            )
            ->order('sortOrder')
            ->find()
            ->toArray();

        if (!empty($data)) {
            // create max
            $max = $entity->get('sortOrder');

            // prepare sql
            $sql = '';
            foreach ($data as $row) {
                // increase max
                $max = $max + 10;

                // prepare id
                $id = $row['id'];

                // prepare sql
                $sql .= "UPDATE attribute SET sort_order='$max' WHERE id='$id';";
            }

            // execute sql
            $this->getEntityManager()->nativeQuery($sql);
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isTypeValueValid(Entity $entity): bool
    {
        if (!empty($entity->get('isMultilang')) && $this->getConfig()->get('isMultilangActive', false) && in_array($entity->get('type'), ['enum', 'multiEnum'])) {
            $count = count($entity->get('typeValue'));
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $field = 'typeValue' . ucfirst(Util::toCamelCase(strtolower($locale)));
                if (count($entity->get($field)) != $count) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function getUnitFieldMeasure(string $fieldName, Entity $entity): string
    {
        if ($fieldName === 'unitDefault') {
            $typeValue = $entity->get('typeValue');

            return empty($typeValue) ? '' : array_shift($typeValue);
        }

        return parent::getUnitFieldMeasure($fieldName, $entity);
    }
}
