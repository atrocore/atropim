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
use Espo\Core\Templates\Services\Hierarchy;
use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Attribute extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'sortOrderInAttributeGroup', 'extensibleEnumId', 'data', 'measureId', 'defaultUnit'];

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

        if (!empty($entity) && $this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
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
        if (property_exists($data, 'sortOrderInAttributeGroup') && property_exists($data, '_sortedIds')) {
            $this->getRepository()->updateSortOrderInAttributeGroup($data->_sortedIds);
            return $this->getEntity($id);
        }

        $entity = $this->getEntityManager()->getRepository('Attribute')->get($id);

        if (!empty($entity)) {
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);

                    $teamsField = "teams{$camelCaseLocale}Ids";
                    if (isset($data->$teamsField)) {
                        $multiLangId = $id . '~' . strtolower($locale);
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

    protected function duplicateProductAttributeValues(Entity $entity, Entity $duplicatingEntity)
    {
        foreach ($duplicatingEntity->get('productAttributeValues') as $item) {
            $record = $this->getEntityManager()->getEntity('ProductAttributeValue');
            $record->set($item->toArray());
            $record->id = null;

            $record->clear('createdAt');
            $record->clear('modifiedAt');
            $record->clear('createdById');
            $record->clear('modifiedById');

            $record->clear('boolValue');
            $record->clear('dateValue');
            $record->clear('datetimeValue');
            $record->clear('intValue');
            $record->clear('floatValue');
            $record->clear('varcharValue');
            $record->clear('textValue');

            $record->set('attributeId', $entity->get('id'));
            $record->set('attributeName', $entity->get('name'));
            $this->getEntityManager()->saveEntity($record);
        }
    }

    protected function init()
    {
        parent::init();

        // add dependencies
        $this->addDependency('language');
    }

    /**
     * @return array
     */
    protected function getAttributesForFilter(): array
    {
        $sql
            = 'SELECT 
                   pf.id        AS classificationId,
                   pf.name      AS classificationName,
                   a.id         AS attributeId,
                   a.name       AS attributeName,
                   a.type       AS attributeType
                FROM attribute AS a
                LEFT JOIN classification_attribute AS pfa ON a.id = pfa.attribute_id AND pfa.deleted = 0
                LEFT JOIN classification AS pf ON pf.id = pfa.classification_id  AND pf.deleted = 0
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
     * Unlink attribute from Classification and Product
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
                ->getRepository('ClassificationAttribute')
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
}
