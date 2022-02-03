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

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Service of Category
 */
class Category extends AbstractService
{
    protected $mandatorySelectAttributeList = ['categoryRoute'];

    public function getCategoryTree(string $parentId): array
    {
        if (empty($parentId)) {
            $where = ['categoryParentId' => null];
        } else {
            $where = ['categoryParentId' => $parentId];
        }

        $result = [];

        foreach ($this->getRepository()->where($where)->order('sortOrder')->find() as $category) {
            $children = $category->get('categories');
            $result[] = [
                'id'             => $category->get('id'),
                'name'           => $category->get('name'),
                'load_on_demand' => !empty($children) && count($children) > 0
            ];
        }

        return $result;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('hasChildren', $entity->hasChildren());

        $channels = $entity->get('channels');
        $channels = !empty($channels) && count($channels) > 0 ? $channels->toArray() : [];

        $entity->set('channelsIds', array_column($channels, 'id'));
        $entity->set('channelsNames', array_column($channels, 'name', 'id'));
    }

    public function isChildCategory(string $categoryId, string $selectedCategoryId): bool
    {
        if (empty($category = $this->getEntityManager()->getEntity('Category', $selectedCategoryId))) {
            return false;
        }

        return in_array($categoryId, explode("|", (string)$category->get('categoryRoute')));
    }

    public function getIdsTree(string $id): array
    {
        $category = $this->getEntityManager()->getEntity('Category', $id);

        $categoriesIds = [];
        $categoriesChild = $category->getChildren()->toArray();

        if (!empty($categoriesChild)) {
            $categoriesChild = $category->getChildren()->toArray();
            $categoriesIds = array_column($categoriesChild, 'id');
        }

        $categoriesIds[] = $category->id;

        return $categoriesIds;
    }

    public function onLinkEntityViaTransaction(string $id, string $link, string $foreignId): void
    {
        if ($link === 'catalogs') {
            $category = $this->getRepository()->get($id);
            if (!empty($category->get('categoryParent'))) {
                throw new BadRequest($this->getInjection('language')->translate('onlyRootCategoryCanBeLinked', 'exceptions', 'Category'));
            }
            foreach ($category->getChildren() as $child) {
                $this->getPseudoTransactionManager()->pushLinkEntityJob('Category', $child->get('id'), 'catalogs', $foreignId);
            }
        }
    }

    public function onUnLinkEntityViaTransaction(string $id, string $link, string $foreignId): void
    {
        if ($link === 'catalogs') {
            $category = $this->getRepository()->get($id);
            if (!empty($category->get('categoryParent'))) {
                throw new BadRequest($this->getInjection('language')->translate('onlyRootCategoryCanBeUnLinked', 'exceptions', 'Category'));
            }

            if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') !== 'cascade') {
                $this->getRepository()->canUnRelateCatalog($category, $foreignId);
            } else {
                foreach ($this->getEntityManager()->getRepository('Catalog')->getProductsIds($foreignId) as $productId) {
                    $this->getPseudoTransactionManager()->pushUnLinkEntityJob('Product', $productId, 'categories', $category->get('id'));
                }
            }

            foreach ($category->getChildren() as $child) {
                $this->getPseudoTransactionManager()->pushUnLinkEntityJob('Category', $child->get('id'), 'catalogs', $foreignId);
            }
        }
    }
}
