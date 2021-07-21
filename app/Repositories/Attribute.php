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
use Treo\Core\Utils\Util;

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

        // get deleted positions
        $deletedPositions = !empty($entity->get('typeValue')) ? $this->getDeletedPositions($entity->get('typeValue')) : [];

        // delete positions
        if (!empty($deletedPositions)) {
            $this->deletePositions($entity, $deletedPositions);
        }

        if (!$entity->isNew()) {
            $this->updateEnumPav($entity, $deletedPositions);
            $this->updateMultiEnumPav($entity, $deletedPositions);
        }

        // set sort order
        if (is_null($entity->get('sortOrder'))) {
            $entity->set('sortOrder', (int)$this->max('sortOrder') + 1);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('sortOrder')) {
            $this->updateSortOrder($entity);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('unique') && $entity->get('unique')) {
            $sql = "SELECT COUNT(*)
                FROM product_attribute_value
                WHERE attribute_id = '{$entity->id}' AND value IS NOT NULL
                    AND deleted = 0
                GROUP BY value, data
                HAVING COUNT(value) > 1 AND COUNT(data) > 1";

            $exists = $this
                ->getEntityManager()
                ->nativeQuery($sql)
                ->fetch(\PDO::FETCH_ASSOC);

            if (!empty($exists)) {
                throw new Error($this->exception('attributeNotHaveUniqueValue'));
            }
        }

        // call parent action
        parent::beforeSave($entity, $options);
    }

    /**
     * @param string $id
     *
     * @return array
     */
    public function getAttributeTeams(string $id): array
    {
        $sql = "
            SELECT t.id, t.name 
            FROM entity_team et 
                INNER JOIN team t 
                    ON t.id = et.team_id 
            WHERE et.entity_type='Attribute' AND et.entity_id='{$id}'";

        return $this->getEntityManager()->nativeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        $this->setInheritedOwnership($entity);

        if ($entity->get('isMultilang') == true && $this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);

                if ($entity->isAttributeChanged("assignedUser{$camelCaseLocale}Id")) {
                    $this->setInheritedOwnershipUser(
                        $entity,
                        "assignedUser{$camelCaseLocale}",
                        $this->getConfig()->get($this->assignedUserOwnership, '')
                    );
                }

                if ($entity->isAttributeChanged("ownerUser{$camelCaseLocale}Id")) {
                    $this->setInheritedOwnershipUser(
                        $entity,
                        "ownerUser{$camelCaseLocale}",
                        $this->getConfig()->get($this->ownerUserOwnership, '')
                    );
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function max($field)
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery("SELECT MAX(sort_order) AS max FROM attribute WHERE deleted=0")
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

    /**
     * @param Entity $attribute
     * @param array  $deletedPositions
     *
     * @return bool
     * @throws BadRequest
     */
    protected function updateEnumPav(Entity $attribute, array $deletedPositions): bool
    {
        if ($attribute->get('type') != 'enum') {
            return true;
        }

        if (!$this->isEnumTypeValueValid($attribute)) {
            return true;
        }

        // old type value
        $oldTypeValue = $attribute->getFetched('typeValue');

        // delete
        foreach ($deletedPositions as $deletedPosition) {
            unset($oldTypeValue[$deletedPosition]);
        }

        // prepare became values
        $becameValues = [];
        foreach (array_values($oldTypeValue) as $k => $v) {
            $becameValues[$v] = $attribute->get('typeValue')[$k];
        }

        /** @var array $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['id', 'value'])
            ->where(['attributeId' => $attribute->get('id')])
            ->find()
            ->toArray();

        foreach ($pavs as $pav) {
            $sqlValues = [];

            /**
             * First, prepare main value
             */
            if (!empty($becameValues[$pav['value']])) {
                $sqlValues[] = "value='{$becameValues[$pav['value']]}'";
            } else {
                $sqlValues[] = "value=null";
            }

            /**
             * Second, update locales
             */
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    if (!empty($becameValues[$pav['value']])) {
                        $locale = ucfirst(Util::toCamelCase(strtolower($language)));
                        $localeValue = "'" . $attribute->get("typeValue{$locale}")[array_search($pav['value'], $attribute->getFetched('typeValue'))] . "'";
                    } else {
                        $localeValue = 'null';
                    }

                    $sqlValues[] = "value_" . strtolower($language) . "=$localeValue";
                }
            }

            /**
             * Third, set to DB
             */
            $this
                ->getEntityManager()
                ->nativeQuery("UPDATE product_attribute_value SET " . implode(",", $sqlValues) . " WHERE id='" . $pav['id'] . "'");
        }

        return true;
    }

    /**
     * @param Entity $attribute
     * @param array  $deletedPositions
     *
     * @return bool
     * @throws BadRequest
     */
    protected function updateMultiEnumPav(Entity $attribute, array $deletedPositions): bool
    {
        if ($attribute->get('type') != 'multiEnum') {
            return true;
        }

        if (!$this->isEnumTypeValueValid($attribute)) {
            return true;
        }

        // old type value
        $oldTypeValue = $attribute->getFetched('typeValue');

        // delete
        foreach ($deletedPositions as $deletedPosition) {
            unset($oldTypeValue[$deletedPosition]);
        }

        // prepare became values
        $becameValues = [];
        foreach (array_values($oldTypeValue) as $k => $v) {
            $becameValues[$v] = $attribute->get('typeValue')[$k];
        }

        /** @var array $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['id', 'value'])
            ->where(['attributeId' => $attribute->get('id')])
            ->find()
            ->toArray();

        foreach ($pavs as $pav) {
            $sqlValues = [];

            /**
             * First, prepare main value
             */
            $values = !empty($pav['value']) ? Json::decode($pav['value'], true) : [];
            if (!empty($values)) {
                $newValues = [];
                foreach ($values as $value) {
                    if (isset($becameValues[$value])) {
                        $newValues[] = $becameValues[$value];
                    }
                }
                $pav['value'] = Json::encode($newValues);
                $values = $newValues;
            }

            $sqlValues[] = "value='" . $pav['value'] . "'";

            /**
             * Second, update locales
             */
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    $locale = ucfirst(Util::toCamelCase(strtolower($language)));
                    $localeValues = [];
                    foreach ($values as $value) {
                        $localeValues[] = $attribute->get("typeValue{$locale}")[array_search($value, $attribute->get('typeValue'))];
                    }
                    $sqlValues[] = "value_" . strtolower($language) . "='" . Json::encode($localeValues) . "'";
                }
            }

            /**
             * Third, set to DB
             */
            $this
                ->getEntityManager()
                ->nativeQuery("UPDATE product_attribute_value SET " . implode(",", $sqlValues) . " WHERE id='" . $pav['id'] . "'");
        }

        return true;
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

    /**
     * @param array $typeValue
     *
     * @return array
     */
    protected function getDeletedPositions(array $typeValue): array
    {
        $deletedPositions = [];
        foreach ($typeValue as $pos => $value) {
            if ($value === 'todel') {
                $deletedPositions[] = $pos;
            }
        }

        return $deletedPositions;
    }

    /**
     * @param Entity $entity
     * @param array  $deletedPositions
     */
    protected function deletePositions(Entity $entity, array $deletedPositions): void
    {
        foreach ($this->getTypeValuesFields() as $field) {
            $typeValue = $entity->get($field);
            foreach ($deletedPositions as $pos) {
                unset($typeValue[$pos]);
            }
            $entity->set($field, array_values($typeValue));
        }
    }

    /**
     * @return array
     */
    protected function getTypeValuesFields(): array
    {
        $fields[] = 'typeValue';
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $fields[] = 'typeValue' . ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        return $fields;
    }
}
