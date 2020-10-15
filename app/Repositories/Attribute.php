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
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;
use Treo\Core\Utils\Util;

/**
 * Class Attribute
 */
class Attribute extends Base
{
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
        // call parent action
        parent::beforeSave($entity, $options);

        // set sort order
        if (is_null($entity->get('sortOrder'))) {
            $entity->set('sortOrder', (int)$this->max('sortOrder') + 1);
        }

        if (!$this->isTypeValueValid($entity)) {
            throw new BadRequest("The number of 'Values' items should be identical for all locales");
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('sortOrder')) {
            $this->updateSortOrder($entity);
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
                throw new Error($this->exception("You can not unlink product family attribute"));
            }
        }
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
}
