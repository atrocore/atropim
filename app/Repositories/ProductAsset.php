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
use Espo\ORM\Entity;

class ProductAsset extends \Espo\Core\Templates\Repositories\Relationship
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && $entity->get('sorting') === null) {
            $last = $this->where(['productId' => $entity->get('productId')])->order('sorting', 'DESC')->findOne();
            $entity->set('sorting', empty($last) ? 0 : (int)$last->get('sorting') + 10);
        }

        if ($entity->get('scope') === 'Global') {
            $entity->set('channelId', '');
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isMainImage') && !empty($entity->get('isMainImage'))) {
            foreach ($this->where(['isMainImage' => true, 'productId' => $entity->get('productId'), 'id!=' => $entity->get('id')])->find() as $productAsset) {
                $productAsset->set('isMainImage', false);
                $this->getEntityManager()->saveEntity($productAsset);
            }
        }
    }

    public function updateSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (count($collection) === 0) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
    {
        $entity = $this->get($parentId);
        if (empty($entity) || empty($entity->get('productId'))) {
            return [];
        }

        $products = $this->getEntityManager()->getRepository('Product')->getChildrenArray($entity->get('productId'));

        if (empty($products)) {
            return [];
        }

        $query = "SELECT *
                  FROM product_asset
                  WHERE deleted=0
                    AND product_id IN ('" . implode("','", array_column($products, 'id')) . "')
                    AND asset_id='{$entity->get('assetId')}'
                    AND scope='{$entity->get('scope')}'";

        if ($entity->get('scope') === 'Channel') {
            $query .= " AND channel_id='{$entity->get('channelId')}'";
        }

        $result = [];
        foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            foreach ($products as $product) {
                if ($product['id'] === $record['product_id']) {
                    $record['childrenCount'] = $product['childrenCount'];
                    break 1;
                }
            }
            $result[] = $record;
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
