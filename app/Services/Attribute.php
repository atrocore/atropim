<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Services\Hierarchy;
use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Attribute extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'sortOrderInAttributeGroup', 'extensibleEnumId', 'data', 'measureId', 'defaultUnit'];

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
