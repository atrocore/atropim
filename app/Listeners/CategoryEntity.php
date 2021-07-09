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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Repositories\Category;
use Treo\Core\EventManager\Event;

/**
 * Class ProductEntity
 */
class CategoryEntity extends AbstractEntityListener
{
    /**
     * Get category route
     *
     * @param Entity $entity
     * @param bool   $isName
     *
     * @return string
     */
    public static function getCategoryRoute(Entity $entity, bool $isName = false): string
    {
        // prepare result
        $result = '';

        // prepare data
        $data = [];

        while (!empty($parent = $entity->get('categoryParent'))) {
            // push id
            if (!$isName) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim($parent->get('name'));
            }

            // to next category
            $entity = $parent;
        }

        if (!empty($data)) {
            if (!$isName) {
                $result = '|' . implode('|', array_reverse($data)) . '|';
            } else {
                $result = implode(' > ', array_reverse($data));
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        // is code valid
        if (!$this->isCodeValid($entity)) {
            throw new BadRequest($this->translate('codeIsInvalid', 'exceptions', 'Global'));
        }

        if ((count($entity->get('catalogs')) > 0 || !empty($entity->get('catalogsIds')))
            && !empty($entity->get('categoryParent'))) {
            throw new BadRequest($this->translate('Only root category can be linked with catalog', 'exceptions', 'Catalog'));
        }

        if (!$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId') && count($entity->getTreeProducts()) > 0) {
                throw new BadRequest($this->exception('parentCategoryHasProducts'));
            }
            if (!empty($parent = $entity->get('categoryParent')) && $parent->get('products')->count() > 0) {
                throw new BadRequest($this->exception('parentCategoryHasProducts'));
            }
        }

        // cascade products relating
        $this->cascadeProductsRelating($entity);
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // build tree
        $this->updateCategoryTree($entity);

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);
    }

    /**
     * @param Event $event
     */
    public function afterRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'products') {
            $this->getProductRepository()->updateProductCategorySortOrder($event->getArgument('foreign'), $event->getArgument('entity'));
            $this->getProductRepository()->linkCategoryChannels($event->getArgument('foreign'), $event->getArgument('entity'));
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'catalogs') {
            $this->getCategoryRepository()->canUnRelateCatalog($event->getArgument('entity'), $event->getArgument('foreign'));
        }
    }


    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'products') {
            $this->getProductRepository()->linkCategoryChannels($event->getArgument('foreign'), $event->getArgument('entity'), true);
        }
    }

    /**
     * @inheritdoc
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Category');
    }

    /**
     * Update category tree
     *
     * @param Entity $entity
     */
    protected function updateCategoryTree(Entity $entity)
    {
        // is has changes
        if ((empty($entity->categoryListener)
            && ($entity->isAttributeChanged('categoryParentId')
                || $entity->isNew()
                || $entity->isAttributeChanged('name')))) {
            // set route for current category
            $entity->set('categoryRoute', self::getCategoryRoute($entity));
            $entity->set('categoryRouteName', self::getCategoryRoute($entity, true));

            $this->saveEntity($entity);

            // update all children
            if (!$entity->isNew()) {
                $children = $this->getEntityChildren($entity->get('categories'), []);
                foreach ($children as $child) {
                    // set route for child category
                    $child->set('categoryRoute', self::getCategoryRoute($child));
                    $child->set('categoryRouteName', self::getCategoryRoute($child, true));
                    $this->saveEntity($child);
                }
            }
        }
    }

    /**
     * Activate parents categories if it needs
     *
     * @param Entity $entity
     */
    protected function activateParents(Entity $entity)
    {
        // is activate action
        $isActivate = $entity->isAttributeChanged('isActive') && $entity->get('isActive');

        if (empty($entity->categoryListener) && $isActivate && !$entity->isNew()) {
            // update all parents
            foreach ($this->getEntityParents($entity, []) as $parent) {
                $parent->set('isActive', true);
                $this->saveEntity($parent);
            }
        }
    }

    /**
     * Deactivate children categories if it needs
     *
     * @param Entity $entity
     */
    protected function deactivateChildren(Entity $entity)
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->categoryListener) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $child->set('isActive', false);
                $this->saveEntity($child);
            }
        }
    }

    /**
     * Save entity
     *
     * @param Entity $entity
     */
    protected function saveEntity(Entity $entity)
    {
        // set flag
        $entity->categoryListener = true;

        $this
            ->getEntityManager()
            ->saveEntity($entity);
    }

    /**
     * Get entity parents
     *
     * @param Entity $category
     * @param array  $parents
     *
     * @return array
     */
    protected function getEntityParents(Entity $category, array $parents): array
    {
        $parent = $category->get('categoryParent');
        if (!empty($parent)) {
            $parents[] = $parent;
            $parents = $this->getEntityParents($parent, $parents);
        }

        return $parents;
    }

    /**
     * Get all children by recursive
     *
     * @param array $entities
     * @param array $children
     *
     * @return array
     */
    protected function getEntityChildren($entities, array $children)
    {
        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $children[] = $entity;
            }
            foreach ($entities as $entity) {
                $children = $this->getEntityChildren($entity->get('categories'), $children);
            }
        }

        return $children;
    }

    /**
     * @param Entity $entity
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function cascadeProductsRelating(Entity $entity)
    {
        if ($entity->isAttributeChanged('channelsIds')) {
            /** @var \Pim\Repositories\Channel $channelRepository */
            $channelRepository = $this->getEntityManager()->getRepository('Channel');

            /** @var EntityCollection $oldChannels */
            $oldChannels = $entity->get('channels');

            /** @var array $newChannelsIds */
            $newChannelsIds = $entity->get('channelsIds');

            foreach ($oldChannels as $oldChannel) {
                if (!in_array($oldChannel->get('id'), $newChannelsIds)) {
                    // unrelate prev
                    $channelRepository->cascadeProductsRelating($entity->get('id'), $oldChannel, true);
                }
            }

            foreach ($newChannelsIds as $newChannelId) {
                if (!in_array($newChannelId, array_column($oldChannels->toArray(), 'id'))) {
                    // relate new
                    $channelRepository->cascadeProductsRelating($entity->get('id'), $channelRepository->get($newChannelId));
                }
            }
        }
    }

    /**
     * @return \Pim\Repositories\Product
     */
    protected function getProductRepository(): \Pim\Repositories\Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }

    /**
     * @return Category
     */
    protected function getCategoryRepository(): Category
    {
        return $this->getEntityManager()->getRepository('Category');
    }
}
