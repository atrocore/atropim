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

use Espo\Core\EventManager\Event;
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

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
    }

    public function updateSortOrderInAttributeGroup(array $ids): void
    {
        foreach ($ids as $k => $id) {
            $id = $this->getPDO()->quote($id);
            $sortOrder = $k * 10;
            $this->getPDO()->exec("UPDATE `attribute` SET sort_order_in_attribute_group=$sortOrder WHERE id=$id");
        }
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        // disable isMultilang for not multilingual attribute types
        if (!in_array($entity->get('type'), \Pim\Module::$multiLangTypes)) {
            $entity->set('isMultilang', false);
        }

        if (empty($entity->get('sortOrderInProduct'))) {
            $entity->set('sortOrderInProduct', time());
        }

        $this->prepareTypeValues($entity);

        // set sort order
        if (is_null($entity->get('sortOrderInAttributeGroup'))) {
            $entity->set('sortOrderInAttributeGroup', (int)$this->max('sortOrderInAttributeGroup') + 1);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('unique') && $entity->get('unique')) {
            $query = "SELECT COUNT(*) 
                      FROM product_attribute_value 
                      WHERE attribute_id='{$entity->id}' 
                        AND deleted=0 %s 
                      GROUP BY %s, `language`, scope, channel_id HAVING COUNT(*) > 1";
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

        if (!$entity->isNew() && $entity->isAttributeChanged('pattern') && !empty($pattern = $entity->get('pattern'))) {
            if (!preg_match("/^\/(.*)\/$/", $pattern)) {
                throw new BadRequest($this->getInjection('language')->translate('regexNotValid', 'exceptions', 'FieldManager'));
            }

            $query = "SELECT DISTINCT varchar_value
                      FROM product_attribute_value 
                      WHERE deleted=0 
                        AND attribute_id='{$entity->get('id')}'
                        AND varchar_value IS NOT NULL 
                        AND varchar_value!=''";

            foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN) as $value) {
                if (!preg_match($pattern, $value)) {
                    throw new BadRequest($this->exception('someAttributeDontMathToPattern'));
                }
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
        if ($entity->isAttributeChanged('virtualProductField') || (!empty($entity->get('virtualProductField') && $entity->isAttributeChanged('code')))) {
            $this->clearCache();
        }

        parent::afterSave($entity, $options);

        $this->setInheritedOwnership($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('virtualProductField'))) {
            $this->clearCache();
        }

        parent::afterRemove($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function max($field)
    {
        $data = $this
            ->getPDO()
            ->query("SELECT MAX(sort_order_in_attribute_group) AS max FROM attribute WHERE deleted=0")
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

    protected function prepareTypeValues(Entity $entity): void
    {
        if (!in_array($entity->get('type'), ['enum', 'multiEnum'])) {
            return;
        }

        if (empty($entity->get('typeValueIds'))) {
            $entity->set('typeValueIds', []);
            return;
        }

        /**
         * Delete not unique option(s)
         */
        $toDelete = [];
        foreach (array_count_values($entity->get('typeValueIds')) as $optionId => $count) {
            if ($count > 1) {
                $toDelete[] = array_search($optionId, $entity->get('typeValueIds'));
            }
        }
        while (count($toDelete) > 0) {
            $key = array_shift($toDelete);
            foreach ($this->getMetadata()->get(['entityDefs', 'Attribute', 'fields']) as $field => $fieldDefs) {
                if (empty($fieldDefs['isTypeValueField'])) {
                    continue;
                }
                $values = $entity->get($field);
                if (is_array($entity->get($field)) && array_key_exists($key, $values)) {
                    unset($values[$key]);
                    $entity->set($field, array_values($values));
                }
            }
        }

        if (!empty($entity->getFetched('typeValueIds')) && !Entity::areValuesEqual('jsonArray', $entity->getFetched('typeValueIds'), $entity->get('typeValueIds'))) {
            foreach ($entity->getFetched('typeValueIds') as $optionId) {
                if (empty($entity->get('typeValueIds')) || !in_array($optionId, $entity->get('typeValueIds'))) {
                    if ($entity->get('type') === 'enum') {
                        $pav = $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->where([
                                'varcharValue' => $optionId,
                                'attributeId'  => $entity->get('id')
                            ])
                            ->findOne();

                        if (!empty($pav)) {
                            throw new BadRequest($this->exception('attributeValueIsInUse'));
                        }
                    } elseif ($entity->get('type') === 'multiEnum') {
                        $pav = $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->where([
                                'textValue*'  => '%"' . $optionId . '"%',
                                'attributeId' => $entity->get('id')
                            ])
                            ->findOne();

                        if (!empty($pav)) {
                            throw new BadRequest($this->exception('attributeValueIsInUse'));
                        }
                    }
                }
            }
        }

        /**
         * Translate type values
         */
        foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
            $languageField = 'typeValue' . ucfirst(Util::toCamelCase(strtolower($language)));
            if ($entity->getFetched($languageField) === $entity->get($languageField)) {
                continue;
            }
            $languageTypeValue = $entity->get($languageField);
            foreach ($entity->get('typeValue') as $k => $value) {
                if (empty($languageTypeValue[$k])) {
                    $event = new Event(['option' => $value, 'language' => $language]);
                    $languageTypeValue[$k] = $this->getInjection('eventManager')->dispatch('AttributeRepository', 'generateEnumOption', $event)->getArgument('option');
                }
            }
            $entity->set($languageField, $languageTypeValue);
        }
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
