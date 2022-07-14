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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Category extends Base
{
    protected $mandatorySelectAttributeList = ['categoryRoute'];

    public function getRoute(string $id): array
    {
        if (empty($category = $this->getRepository()->get($id))) {
            return [];
        }

        if (empty($categoryRoute = $category->get('categoryRoute'))) {
            return [];
        }

        $route = explode('|', $categoryRoute);
        array_shift($route);
        array_pop($route);

        return $route;
    }

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

        return [
            'list' => $result,
            'total' => $this->getRepository()->getChildrenCount($parentId)
        ];
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

    public function findLinkedEntities($id, $link, $params)
    {
        $result = parent::findLinkedEntities($id, $link, $params);

        /**
         * Mark channels as inherited from parent category
         */
        if ($link === 'channels' && $result['total'] > 0 && !empty($channelsIds = $this->getRepository()->getParentChannelsIds($id))) {
            foreach ($result['collection'] as $channel) {
                $channel->set('isInherited', in_array($channel->get('id'), $channelsIds));
            }
        }

        return $result;
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
}
