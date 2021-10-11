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

use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Service of Category
 */
class Category extends AbstractService
{
    /**
     * @var array
     */
    protected $mandatorySelectAttributeList = ['categoryRoute'];

    /**
     * @var array
     */
    private $roots = [];

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

    /**
     * Get category entity
     *
     * @param string $id
     *
     * @return array
     * @throws Forbidden
     */
    public function getEntity($id = null)
    {
        // call parent
        $entity = parent::getEntity($id);

        if (!empty($entity)) {
            /** @var array $channels */
            $channels = $this->getRootChannels($entity)->toArray();

            // set hasChildren param
            $entity->set('hasChildren', $entity->hasChildren());
            $entity->set('channelsIds', array_column($channels, 'id'));
            $entity->set('channelsNames', array_column($channels, 'name', 'id'));
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function findEntities($params)
    {
        $result = parent::findEntities($params);

        /**
         * Set channels to children categories
         */
        if (!empty($result['total'])) {
            $roots = [];
            foreach ($result['collection'] as $category) {
                if (empty($category->get('channelsIds'))) {
                    /** @var array $channels */
                    $channels = $this->getRootChannels($category)->toArray();

                    $category->set('channelsIds', array_column($channels, 'id'));
                    $category->set('channelsNames', array_column($channels, 'name', 'id'));
                }
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function findLinkedEntities($id, $link, $params)
    {
        if ($link == 'catalogs' || $link == 'channels') {
            $category = $this->getEntityManager()->getEntity('Category', $id);
            if (!empty($category)) {
                $id = $category->getRoot()->get('id');
            }
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    /**
     * Is child category
     *
     * @param string $categoryId
     * @param string $selectedCategoryId
     *
     * @return bool
     */
    public function isChildCategory(string $categoryId, string $selectedCategoryId): bool
    {
        // get category
        if (empty($category = $this->getEntityManager()->getEntity('Category', $selectedCategoryId))) {
            return false;
        }

        return in_array($categoryId, explode("|", (string)$category->get('categoryRoute')));
    }

    /**
     * Get id parent category and ids children category
     *
     * @param string $id
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getIdsTree(string $id): array
    {
        /** @var \Pim\Entities\Category $category */
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

    /**
     * @param Entity $category
     *
     * @return EntityCollection
     */
    protected function getRootChannels(Entity $category): EntityCollection
    {
        $categoryRoute = explode('|', $category->get('categoryRoute'));
        $categoryRootId = (isset($categoryRoute[1])) ? $categoryRoute[1] : $category->get('id');
        if (!isset($this->roots[$categoryRootId])) {
            $this->roots[$categoryRootId] = $this->getEntityManager()->getEntity('Category', $categoryRootId);
        }

        return $this->roots[$categoryRootId]->get('channels');
    }
}
