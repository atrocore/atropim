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

use Espo\Core\Templates\Services\Hierarchy;
use Espo\ORM\Entity;
use Espo\Services\Record;

class Category extends Hierarchy
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

    public function createEntity($attachment)
    {
        return Record::createEntity($attachment);
    }

    public function updateEntity($id, $data)
    {
        return Record::updateEntity($id, $data);
    }

    public function linkEntity($id, $link, $foreignId)
    {
        return Record::linkEntity($id, $link, $foreignId);
    }

    public function deleteEntity($id)
    {
        return Record::deleteEntity($id);
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        return Record::unlinkEntity($id, $link, $foreignId);
    }

    protected function createTreeBranches(Entity $entity, array &$treeBranches): void
    {
        $parent = $entity->get('categoryParent');
        if (empty($parent)) {
            $treeBranches[] = $entity;
        } else {
            $parent->child = $entity;
            $this->createTreeBranches($parent, $treeBranches);
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        Record::prepareEntityForOutput($entity);

        $entity->set('hasChildren', $entity->hasChildren());

        $channels = $entity->get('channels');
        $channels = !empty($channels) && count($channels) > 0 ? $channels->toArray() : [];

        $entity->set('channelsIds', array_column($channels, 'id'));
        $entity->set('channelsNames', array_column($channels, 'name', 'id'));
    }

    public function findLinkedEntities($id, $link, $params)
    {
        $result = Record::findLinkedEntities($id, $link, $params);

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
}
