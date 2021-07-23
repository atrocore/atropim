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

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Channel
 */
class Channel extends Base
{
    public function getProductsRelationData(string $id): array
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT product_id as productId, is_active AS isActive, from_category_tree as isFromCategoryTree FROM product_channel WHERE channel_id='$id' AND deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            $result[$row['productId']] = $row;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getUsedLocales(): array
    {
        $locales = [];
        foreach ($this->select(['locales'])->find()->toArray() as $item) {
            $locales = array_merge($locales, $item['locales']);
        }

        return array_values(array_unique($locales));
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('locales'))) {
            $entity->set('locales', ['mainLocale']);
        }

        if ($entity->isAttributeChanged('categoryId')) {
            // unrelate from previous tree
            if (!empty($prevRootId = $entity->getFetched('categoryId'))) {
                $prevRoot = $this->getEntityManager()->getEntity('Category', $prevRootId);
                foreach ($prevRoot->getTreeProducts() as $product) {
                    $this->getEntityManager()->getRepository('Product')->unrelateChannel($product, $entity);
                }
            }

            // relate to new tree
            if (!empty($rootId = $entity->get('categoryId'))) {
                $root = $this->getEntityManager()->getEntity('Category', $rootId);
                foreach ($root->getTreeProducts() as $product) {
                    $this->getEntityManager()->getRepository('Product')->relateChannel($product, $entity, true);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        parent::afterUnrelate($entity, $relationName, $foreign, $options);

        if ($relationName == 'products') {
            $this->getEntityManager()->nativeQuery("DELETE FROM product_channel WHERE deleted=1");
        }
    }
}
