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
use Espo\ORM\EntityCollection;
use Pim\Listeners\AbstractEntityListener;

/**
 * Class Category
 */
class Category extends AbstractRepository
{
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

    public function getNotRelatedWithCatalogsTreeIds(): array
    {
        return $this
            ->getEntityManager()
            ->nativeQuery("SELECT id FROM category WHERE deleted=0 AND category_parent_id IS NULL AND id NOT IN (SELECT category_id FROM catalog_category WHERE deleted=0)")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function canUnRelateCatalog(Entity $category, Entity $catalog): void
    {
        /** @var array $productsIds */
        $productsIds = array_column($catalog->get('products')->toArray(), 'id');

        if (!empty($productsIds)) {
            $categoriesIds = array_column($category->getChildren()->toArray(), 'id');
            $categoriesIds[] = $category->get('id');

            $categoriesIds = implode("','", $categoriesIds);
            $productsIds = implode("','", $productsIds);

            $total = $this
                ->getEntityManager()
                ->nativeQuery("SELECT COUNT('id') as total FROM product_category WHERE product_id IN ('$productsIds') AND category_id IN ('$categoriesIds') AND deleted=0")
                ->fetch(\PDO::FETCH_COLUMN);

            if (!empty($total)) {
                throw new BadRequest($this->exception('categoryCannotBeUnRelatedFromCatalog'));
            }
        }
    }

    /**
     * @param Entity|string $category
     * @param Entity|string $catalog
     */
    public function tryToUnRelateCatalog($category, $catalog): void
    {
        if (is_bool($category) || is_bool($catalog)) {
            return;
        }

        if (!$category instanceof Entity) {
            $category = $this->getEntityManager()->getEntity('Category', $category);
        }

        if (!$catalog instanceof Entity) {
            $catalog = $this->getEntityManager()->getEntity('Catalog', $catalog);
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') !== 'cascade') {
            $this->canUnRelateCatalog($category, $catalog);
        } else {
            $this->cascadeUnRelateCatalog($category, $catalog);
        }
    }

    public function cascadeUnRelateCatalog(Entity $category, Entity $catalog): void
    {
        $products = $catalog->get('products');
        if (count($products) > 0) {
            $root = $category->getRoot();
            $children = $root->getChildren();
            foreach ($products as $product) {
                $this->getProductRepository()->unrelate($product, 'categories', $root);
                if (count($children) > 0) {
                    foreach ($children as $cat) {
                        $this->getProductRepository()->unrelate($product, 'categories', $cat);
                    }
                }
            }
        }
    }

    public function relate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName === 'channels') {
            if (!empty($channel = $this->getForeignChannel($foreign))) {
                $channel->set('categoryId', $entity->get('id'));
                $this->getEntityManager()->saveEntity($channel);
            }

            return true;
        }

        return parent::relate($entity, $relationName, $foreign, $data, $options);
    }

    public function unrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'channels') {
            if (!empty($channel = $this->getForeignChannel($foreign))) {
                $channel->set('categoryId', null);
                $this->getEntityManager()->saveEntity($channel);
            }

            return true;
        }

        return parent::unrelate($entity, $relationName, $foreign, $options);
    }

    protected function getForeignChannel($foreign): ?Entity
    {
        if (is_string($foreign)) {
            $channelId = $foreign;
        } elseif ($foreign instanceof Entity) {
            $channelId = $foreign->get('id');
        } else {
            $channelId = null;
        }

        if (!empty($channelId)) {
            $channel = $this->getEntityManager()->getEntity('Channel', $channelId);
            if (!empty($channel)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        // is code valid
        if (!$this->isCodeValid($entity)) {
            throw new BadRequest($this->translate('codeIsInvalid', 'exceptions', 'Global'));
        }

        if ($entity->isAttributeChanged('categoryParentId') && !$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            if (!empty($parent = $entity->get('categoryParent')) && count($parent->get('products')) > 0) {
                throw new BadRequest($this->exception('parentCategoryHasProducts'));
            }
        }

        if ($entity->isNew()) {
            $entity->set('sortOrder', time());
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId')) {
            $products = $entity->getTreeProducts();
            $hasProducts = count($products) > 0;

            // unrelate channels from category
            foreach ($entity->get('channels') as $channel) {
                $this->unrelate($entity, 'channels', $channel);
            }

            // unrelate channels from products
            if ($hasProducts) {
                foreach ($products as $product) {
                    $this->getProductRepository()->unrelateCategoryTreeChannels($product);
                }
            }

            if (empty($entity->get('categoryParentId'))) {
                if (!empty($entity->getFetched('categoryParentId'))) {
                    $fetchedRoot = $this->getEntityManager()->getEntity('Category', $entity->getFetched('categoryParentId'))->getRoot();
                    foreach ($fetchedRoot->get('catalogs') as $catalog) {
                        $this->relate($entity, 'catalogs', $catalog);
                    }
                }
            } else {
                if (empty($entity->getFetched('categoryParentId'))) {
                    $fetchedRoot = $entity;
                } else {
                    $fetchedRoot = $this->getEntityManager()->getEntity('Category', $entity->getFetched('categoryParentId'))->getRoot();
                }

                $root = $this->getEntityManager()->getEntity('Category', $entity->get('categoryParentId'))->getRoot();

                foreach ($fetchedRoot->get('catalogs') as $catalog) {
                    $this->relate($root, 'catalogs', $catalog, null, ['skipCategoryParentValidation' => true]);
                }

                // relate channel to products
                if ($hasProducts) {
                    $channels = $root->get('channels');
                    if (count($channels) > 0) {
                        foreach ($products as $product) {
                            foreach ($channels as $channel) {
                                $this->getProductRepository()->relateChannel($product, $channel, true);
                            }
                        }
                    }
                }

                foreach ($entity->get('catalogs') as $catalog) {
                    $this->unrelate($entity, 'catalogs', $catalog);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('_position'))) {
            $this->updateSortOrderInTree($entity);
        }

        // build tree
        $this->updateCategoryTree($entity);

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);

        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') !== 'cascade') {
            if ($entity->get('products')->count() > 0) {
                throw new BadRequest($this->exception("categoryHasProducts"));
            }

            if ($entity->get('categories')->count() > 0) {
                throw new BadRequest($this->exception("categoryHasChildCategoryAndCantBeDeleted"));
            }
        } else {
            $products = $entity->get('products');
            if (count($products) > 0) {
                $channels = $entity->getRoot()->get('channels');
                foreach ($products as $product) {
                    $this->unrelate($entity, 'products', $product);
                    if (count($channels) > 0) {
                        foreach ($channels as $channel) {
                            $this->getProductRepository()->unrelate($product, 'channels', $channel);
                        }
                    }
                }
            }

            $children = $entity->get('categories');
            if (count($children) > 0) {
                foreach ($children as $child) {
                    $this->getEntityManager()->removeEntity($child);
                }
            }
        }

        parent::beforeRemove($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'catalogs' && empty($options['skipCategoryParentValidation'])) {
            if (!empty($entity->get('categoryParent'))) {
                throw new BadRequest($this->exception('Only root category can be linked with catalog'));
            }
        }

        if ($relationName == 'products') {
            $this->getProductRepository()->isCategoryFromCatalogTrees($foreign, $entity);
            $this->getProductRepository()->isProductCanLinkToNonLeafCategory($entity);
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::afterRelate($entity, $relationName, $foreign, $data, $options);

        if ($relationName === 'products') {
            $this->getProductRepository()->updateProductCategorySortOrder($foreign, $entity);
            $this->getProductRepository()->linkCategoryChannels($foreign, $entity);
        }
    }

    /**
     * @inheritDoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'catalogs') {
            $this->tryToUnRelateCatalog($entity, $foreign);
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'channels') {
            foreach ($entity->getTreeProducts() as $product) {
                $this->getProductRepository()->unrelateChannel($product, $foreign);
            }
        }

        if ($relationName === 'products') {
            $this->getProductRepository()->linkCategoryChannels($foreign, $entity, true);
        }

        parent::afterUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    /**
     * @param Entity $entity
     */
    protected function updateSortOrderInTree(Entity $entity): void
    {
        // prepare sort order
        $sortOrder = 0;

        // prepare data
        $data = [];

        if ($entity->get('_position') == 'after') {
            // prepare sort order
            $sortOrder = $this->select(['sortOrder'])->where(['id' => $entity->get('_target')])->findOne()->get('sortOrder');

            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => [$entity->get('_target'), $entity->get('id')],
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();

            // increase sort order
            $sortOrder = $sortOrder + 10;

        } elseif ($entity->get('_position') == 'inside') {
            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => $entity->get('id'),
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();
        }

        // prepare data
        $data = array_merge([$entity->get('id')], array_column($data, 'id'));

        // prepare sql
        $sql = '';
        foreach ($data as $id) {
            // prepare sql
            $sql .= "UPDATE category SET sort_order=$sortOrder WHERE id='$id';";

            // increase sort order
            $sortOrder = $sortOrder + 10;
        }

        // execute sql
        $this->getEntityManager()->nativeQuery($sql);
    }

    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Category');
    }

    protected function translate(string $key, string $label = 'labels', string $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function getProductRepository(): Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }

    protected function isCodeValid(Entity $entity): bool
    {
        if (!$entity->isAttributeChanged('code')) {
            return true;
        }

        if (empty($entity->get('code'))) {
            return true;
        }

        if (!preg_match(AbstractEntityListener::$codePattern, $entity->get('code'))) {
            return false;
        }

        return empty($this->where(['id!=' => $entity->get('id'), 'code' => $entity->get('code')])->findOne());
    }

    protected function activateParents(Entity $entity): void
    {
        // is activate action
        $isActivate = $entity->isAttributeChanged('isActive') && $entity->get('isActive');

        if (empty($entity->recursiveSave) && $isActivate && !$entity->isNew()) {
            // update all parents
            foreach ($this->getEntityParents($entity, []) as $parent) {
                $parent->set('isActive', true);
                $this->saveEntity($parent);
            }
        }
    }

    protected function deactivateChildren(Entity $entity): void
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->recursiveSave) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $child->set('isActive', false);
                $this->saveEntity($child);
            }
        }
    }

    protected function saveEntity(Entity $entity): void
    {
        // set flag
        $entity->recursiveSave = true;

        $this->getEntityManager()->saveEntity($entity);
    }

    protected function getEntityParents(Entity $category, array $parents): array
    {
        $parent = $category->get('categoryParent');
        if (!empty($parent)) {
            $parents[] = $parent;
            $parents = $this->getEntityParents($parent, $parents);
        }

        return $parents;
    }

    protected function getEntityChildren(EntityCollection $entities, array $children): array
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

    protected function updateCategoryTree(Entity $entity): void
    {
        if (!empty($entity->recursiveSave)) {
            return;
        }

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
