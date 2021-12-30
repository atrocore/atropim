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

use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;
use Espo\Core\Utils\Util;

class Attribute extends AbstractService
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'data'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getDataFields() as $name => $value) {
            $entity->set($name, $value);
        }
    }

    /**
     * @inheritDoc
     */
    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        $entity = $this->getRepository()->get($id);

        if ($this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                if (!empty($ownerUserId = $entity->get("ownerUser{$camelCaseLocale}Id"))) {
                    $ownerUser = $this->getEntityManager()->getEntity('User', $ownerUserId);
                    $entity->set("ownerUser{$camelCaseLocale}Name", $ownerUser->get('name'));
                } else {
                    $entity->set("ownerUser{$camelCaseLocale}Name", null);
                }

                if (!empty($assignedUserId = $entity->get("assignedUser{$camelCaseLocale}Id"))) {
                    $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                    $entity->set("assignedUser{$camelCaseLocale}Name", $assignedUser->get('name'));
                } else {
                    $entity->set("assignedUser{$camelCaseLocale}Name", null);
                }
            }
        }

        if (!empty($entity) && !empty($id)) {
            $this->loadAdditionalFields($entity);

            if (!$this->getAcl()->check($entity, 'read')) {
                throw new Forbidden();
            }
        }
        if (!empty($entity)) {
            $this->prepareEntityForOutput($entity);
        }

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
    }

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        $entity = $this->getEntityManager()->getRepository('Attribute')->get($id);

        if (!empty($entity)) {
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);

                    $teamsField = "teams{$camelCaseLocale}Ids";
                    if (isset($data->$teamsField)) {
                        $multiLangId = $id . ProductAttributeValue::LOCALE_IN_ID_SEPARATOR . strtolower($locale);
                        $this->getRepository()->changeMultilangTeams($multiLangId, 'Attribute', $data->$teamsField);

                        $this
                            ->getEntityManager()
                            ->getRepository('Attribute')
                            ->setInheritedOwnershipTeams($entity, $data->$teamsField, $locale);
                    }
                }
            }
        }

        return parent::updateEntity($id, $data);
    }

    /**
     * Get filters
     *
     * @return array
     */
    public function getFiltersData(): array
    {
        // prepare result
        $result = [];

        // get all attributes
        if (!empty($data = $this->getAttributesForFilter())) {
            // get multilang fields
            $multilangFields = $this->getMultilangFields();

            // prepare no family data
            $noFamilyData = [
                'id'   => 'all',
                'name' => $this->getTranslate('All', 'filterLabels', 'Attribute'),
                'rows' => []
            ];

            foreach ($data as $row) {
                // skip multilang types
                if (in_array($row['attributeType'], $multilangFields)) {
                    continue;
                }

                // prepare attribute typeValue param
                $attributeTypeValue = [];
                if (!empty($row['attributeTypeValue'])) {
                    $attributeTypeValue = json_decode($row['attributeTypeValue'], true);
                }

                if (!empty($row['productFamilyId'])) {
                    $result[$row['productFamilyId']]['id'] = $row['productFamilyId'];
                    $result[$row['productFamilyId']]['name'] = $row['productFamilyName'];
                    $result[$row['productFamilyId']]['rows'][$row['attributeId']] = [
                        'attributeId' => $row['attributeId'],
                        'name'        => $row['attributeName'],
                        'type'        => $row['attributeType'],
                        'typeValue'   => $attributeTypeValue
                    ];
                }

                // push to all
                $noFamilyData['rows'][$row['attributeId']] = [
                    'attributeId' => $row['attributeId'],
                    'name'        => $row['attributeName'],
                    'type'        => $row['attributeType'],
                    'typeValue'   => $attributeTypeValue
                ];
            }
            $noFamilyData['rows'] = array_values($noFamilyData['rows']);

            // prepare result
            $result = array_values($result);
            foreach ($result as $k => $v) {
                $result[$k]['rows'] = array_values($v['rows']);
            }

            // push no family to the end of result
            if (!empty($noFamilyData['rows'])) {
                $result[] = $noFamilyData;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAttributesIdsFilter(): array
    {
        $result = [];

        $attributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->find()
            ->toArray();

        if (count($attributes) > 0) {
            $result = array_column($attributes, 'id');
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getAttributesForFilter(): array
    {
        $sql
            = 'SELECT 
                   pf.id        AS productFamilyId,
                   pf.name      AS productFamilyName,
                   a.id         AS attributeId,
                   a.name       AS attributeName,
                   a.type       AS attributeType,
                   a.type_value AS attributeTypeValue
                FROM attribute AS a
                LEFT JOIN product_family_attribute AS pfa ON a.id = pfa.attribute_id AND pfa.deleted = 0
                LEFT JOIN product_family AS pf ON pf.id = pfa.product_family_id  AND pf.deleted = 0
                WHERE a.deleted=0 
                  AND a.id IN (SELECT attribute_id FROM product_attribute_value WHERE deleted=0)';

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        return (!empty($data)) ? $data : [];
    }

    /**
     * Get multilang fields
     *
     * @return array
     */
    protected function getMultilangFields(): array
    {
        // get config
        $config = $this->getConfig()->get('modules');

        return (!empty($config['multilangFields'])) ? array_keys($config['multilangFields']) : [];
    }

    /**
     * @param Entity $entity
     */
    protected function afterDeleteEntity(Entity $entity)
    {
        // call parent action
        parent::afterDeleteEntity($entity);

        // unlink
        $this->unlinkAttribute([$entity->get('id')]);
    }

    /**
     * @param array $idList
     */
    protected function afterMassRemove(array $idList)
    {
        // call parent action
        parent::afterMassRemove($idList);

        // unlink
        $this->unlinkAttribute($idList);
    }


    /**
     * Unlink attribute from ProductFamily and Product
     *
     * @param array $ids
     *
     * @return bool
     */
    protected function unlinkAttribute(array $ids): bool
    {
        // prepare data
        $result = false;

        if (!empty($ids)) {
            // remove from product families
            $this
                ->getEntityManager()
                ->getRepository('ProductFamilyAttribute')
                ->where([
                    'attributeId' => $ids
                ])
                ->removeCollection();

            // remove from products
            $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->where([
                    'attributeId' => $ids
                ])
                ->removeCollection();

            // prepare result
            $result = true;
        }

        return $result;
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }
}
