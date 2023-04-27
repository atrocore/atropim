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
        $this->getInjection('dataManager')->setCacheData('attribute_product_fields', null);
    }

    public function updateSortOrderInAttributeGroup(array $ids): void
    {
        foreach ($ids as $k => $id) {
            $id = $this->getPDO()->quote($id);
            $sortOrder = $k * 10;
            $this->getPDO()->exec("UPDATE `attribute` SET sort_order_in_attribute_group=$sortOrder WHERE id=$id");
        }
    }

    public function getMultilingualAttributeTypes(): array
    {
        $attributes = [];
        foreach ($this->getMetadata()->get(['attributes'], []) as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if (!in_array($entity->get('type'), $this->getMultilingualAttributeTypes())) {
            $entity->set('isMultilang', false);
        }

        if ($entity->get('sortOrderInProduct') === null) {
            $entity->set('sortOrderInProduct', time());
        }

        if ($entity->get('sortOrderInAttributeGroup') === null) {
            $entity->set('sortOrderInAttributeGroup', time());
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
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, "exceptions", "Attribute");
    }

    protected function getUnitFieldMeasure(string $fieldName, Entity $entity): string
    {
        if ($fieldName === 'unitDefault') {
            $measure = $entity->getDataField('measure');

            return empty($measure) ? '' : $measure;
        }

        return parent::getUnitFieldMeasure($fieldName, $entity);
    }
}
