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

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Listeners\AbstractEntityListener;

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
            if (!$isName || empty($parent->get('name'))) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim((string)$parent->get('name'));
            }

            // to next category
            $entity = $parent;
        }

        if (!empty($data)) {
            if (!$isName) {
                $result = '|' . implode('|', array_reverse($data)) . '|';
            } else {
                $result = implode(' / ', array_reverse($data));
            }
        }

        return $result;
    }

    public function getParentChannelsIds(string $categoryId): array
    {
        $categoryId = $this->getPDO()->quote($categoryId);

        $query = "SELECT channel_id 
                  FROM `category_channel` 
                  WHERE deleted=0 
                    AND category_id IN (SELECT category_parent_id FROM `category` WHERE deleted=0 AND id=$categoryId)";

        return $this
            ->getPDO()
            ->query($query)
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getNotRelatedWithCatalogsTreeIds(): array
    {
        return $this
            ->getEntityManager()
            ->nativeQuery("SELECT id FROM category WHERE deleted=0 AND category_parent_id IS NULL AND id NOT IN (SELECT category_id FROM catalog_category WHERE deleted=0)")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function canUnRelateCatalog(Entity $category, string $catalogId): void
    {
        if (!$this->getEntityManager()->getRepository('Catalog')->hasProducts($catalogId)) {
            return;
        }

        $categoriesIds = array_column($category->getChildren()->toArray(), 'id');
        $categoriesIds[] = $category->get('id');


        $categoriesIds = implode("','", $categoriesIds);
        $catalogId = $this->getPDO()->quote($catalogId);

        $records = $this
            ->getPDO()
            ->query(
                "SELECT id 
                 FROM product_category 
                 WHERE product_id IN (SELECT id FROM product WHERE catalog_id=$catalogId AND deleted=0) 
                   AND category_id IN ('$categoriesIds') 
                   AND deleted=0 
                 LIMIT 0,1"
            )
            ->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($records)) {
            throw new BadRequest($this->exception('categoryCannotBeUnRelatedFromCatalog'));
        }
    }

    public function relateCatalogs(Entity $category, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $catalogId = $foreign;
        if ($foreign instanceof Entity) {
            $catalogId = $foreign->get('id');
        }

        if (!empty($options['pseudoTransactionId']) || empty($options['pseudoTransactionManager'])) {
            return $this->getMapper()->addRelation($category, 'catalogs', $catalogId);
        }

        if (!empty($category->get('categoryParent'))) {
            throw new BadRequest($this->exception('onlyRootCategoryCanBeLinked'));
        }

        $this->getPDO()->beginTransaction();
        try {
            $result = $this->getMapper()->addRelation($category, 'catalogs', $catalogId);
            foreach ($category->getChildren() as $child) {
                $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $child->get('id'), 'catalogs', $catalogId);
            }
            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function unrelateCatalogs(Entity $category, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $catalogId = $foreign;
        if ($foreign instanceof Entity) {
            $catalogId = $foreign->get('id');
        }

        if (!empty($options['pseudoTransactionId']) || empty($options['pseudoTransactionManager'])) {
            return $this->getMapper()->removeRelation($category, 'catalogs', $catalogId);
        }

        if (!empty($category->get('categoryParent'))) {
            throw new BadRequest($this->exception('onlyRootCategoryCanBeUnLinked'));
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') !== 'cascade') {
            $this->canUnRelateCatalog($category, $catalogId);
        }

        $this->getPDO()->beginTransaction();

        try {
            $result = $this->getMapper()->removeRelation($category, 'catalogs', $catalogId);

            foreach ($category->getChildren() as $child) {
                $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $child->get('id'), 'catalogs', $catalogId);
                $this
                    ->getPDO()
                    ->exec("DELETE FROM `product_category` WHERE category_id='{$child->get('id')}' AND product_id IN (SELECT id FROM product WHERE catalog_id='$catalogId')");
            }

            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function relateChannels(Entity $category, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $channelId = $foreign;
        if ($foreign instanceof Entity) {
            $channelId = $foreign->get('id');
        }

        $this->getPDO()->beginTransaction();
        try {
            $result = $this->getMapper()->addRelation($category, 'channels', $channelId);
            if (!empty($products = $category->get('products')) && count($products) > 0) {
                foreach ($products as $product) {
                    $this->getEntityManager()->getRepository('ProductChannel')->createRelationshipViaCategory($product, $category);
                }
            }
            if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
                foreach ($category->getChildren() as $child) {
                    $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $child->get('id'), 'channels', $channelId);
                }
            }
            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function unrelateChannels(Entity $category, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $channelId = $foreign;
        if ($foreign instanceof Entity) {
            $channelId = $foreign->get('id');
        }

        $this->getPDO()->beginTransaction();
        try {
            if (!empty($products = $category->get('products')) && count($products) > 0) {
                foreach ($products as $product) {
                    $this->getEntityManager()->getRepository('ProductChannel')->deleteRelationshipViaCategory($product, $category);
                }
            }
            $result = $this->getMapper()->removeRelation($category, 'channels', $channelId);
            if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
                foreach ($category->getChildren() as $child) {
                    $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $child->get('id'), 'channels', $channelId);
                }
            }
            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function relateProducts(Entity $category, $product, $data, $options)
    {
        if (is_bool($product)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        if (is_string($product)) {
            $product = $this->getProductRepository()->get($product);
        }

        $this->getProductRepository()->isCategoryFromCatalogTrees($product, $category);
        $this->getProductRepository()->isProductCanLinkToNonLeafCategory($category);

        $this->getPDO()->beginTransaction();
        try {
            $result = $this->getMapper()->addRelation($category, 'products', $product->get('id'));
            $this->getProductRepository()->updateProductCategorySortOrder($product, $category);
            $this->getEntityManager()->getRepository('ProductChannel')->createRelationshipViaCategory($product, $category);
            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function unrelateProducts(Entity $category, $product, $options)
    {
        if (is_bool($product)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        if (is_string($product)) {
            $product = $this->getProductRepository()->get($product);
        }

        $this->getPDO()->beginTransaction();
        try {
            $result = $this->getMapper()->removeRelation($category, 'products', $product->get('id'));
            $this->getEntityManager()->getRepository('ProductChannel')->deleteRelationshipViaCategory($product, $category);
            $this->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null): array
    {
        $select = 'c.*';
        if ($withChildrenCount) {
            $select .= ", (SELECT COUNT(c1.id) FROM category c1 WHERE c1.category_parent_id=c.id AND c1.deleted=0) as childrenCount";
        }

        if (empty($parentId)) {
            $query = "SELECT {$select} 
                      FROM category c
                      WHERE c.category_parent_id IS NULL
                      AND c.deleted=0
                      ORDER BY c.sort_order, c.id";
        } else {
            $parentId = $this->getPDO()->quote($parentId);
            $query = "SELECT {$select} 
                      FROM category c
                      WHERE c.category_parent_id=$parentId
                      AND c.deleted=0
                      ORDER BY c.sort_order, c.id";
        }

        if (!is_null($offset) && !is_null($maxSize)) {
            $query .= " LIMIT $maxSize OFFSET $offset";
        }

        return $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return int
     */
    public function getChildrenCount(string $parentId): int
    {
        if (empty($parentId)) {
            $query = "SELECT COUNT(id) as count
                      FROM `category` c
                      WHERE c.category_parent_id IS NULL AND c.deleted=0";
        } else {
            $query = "SELECT COUNT(id) as count
                      FROM `category` c
                      WHERE c.category_parent_id = '$parentId' AND c.deleted=0";
        }

        return (int)$this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC)['count'];
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

        if ($entity->isAttributeChanged('categoryParentId')) {
            $childrenIds = array_column($entity->getChildren()->toArray(), 'id');
            if ($entity->get('categoryParentId') === $entity->get('id') || in_array($entity->get('categoryParentId'), $childrenIds)) {
                throw new BadRequest($this->exception('youCanNotChooseChildCategory'));
            }

            if (!$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
                $categoryParent = $entity->get('categoryParent');
                if (!empty($categoryParent)) {
                    $categoryParentProducts = $categoryParent->get('products');
                    if (!empty($categoryParentProducts) && count($categoryParentProducts) > 0) {
                        throw new BadRequest($this->exception('parentCategoryHasProducts'));
                    }
                }
            }
        }

        if ($entity->isNew() && !empty($categoryParent = $entity->get('categoryParent'))) {
            $parentCatalogs = $categoryParent->get('catalogs');
            $parentCatalogsIds = empty($parentCatalogs) ? [] : array_column($parentCatalogs->toArray(), 'id');
            $entity->set('catalogsIds', $parentCatalogsIds);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId') && !empty($parent = $entity->get('categoryParent'))) {
            $categoryCatalogs = array_column($entity->get('catalogs')->toArray(), 'id');
            sort($categoryCatalogs);

            $parentCatalogs = array_column($parent->get('catalogs')->toArray(), 'id');
            sort($parentCatalogs);

            if ($categoryCatalogs !== $parentCatalogs) {
                throw new BadRequest($this->exception('catalogsShouldBeSame'));
            }
        }

        if ($entity->isNew()) {
            $entity->set('sortOrder', time());
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

        // relate parent channels
        if ($entity->isNew() && !empty($parent = $entity->get('categoryParent'))) {
            if (!empty($parentChannels = $parent->get('channels')) && count($parentChannels) > 0) {
                foreach ($parentChannels as $parentChannel) {
                    $this->relate($entity, 'channels', $parentChannel);
                }
            }
        }

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);

        parent::afterSave($entity, $options);
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') !== 'cascade') {
            if (!empty($products = $entity->get('products')) && count($products) > 0) {
                throw new BadRequest($this->exception("categoryHasProducts"));
            }

            if (!empty($categories = $entity->get('categories')) && count($categories) > 0) {
                throw new BadRequest($this->exception("categoryHasChildCategoryAndCantBeDeleted"));
            }
        }

        $result = $this->getMapper()->delete($entity);
        $this->getPDO()->exec("DELETE FROM `product_category` WHERE category_id='{$entity->get('id')}'");
        $this->getPDO()->exec("DELETE FROM `category_channel` WHERE category_id='{$entity->get('id')}'");

        foreach ($this->where(['categoryParentId' => $entity->get('id')])->find() as $child) {
            $this->remove($child, $options);
        }

        if ($result) {
            $this->afterRemove($entity, $options);
        }

        return $result;
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

        $this
            ->getConnection()
            ->createQueryBuilder()
            ->update('category')
            ->set('category_route', ':categoryRoute')->setParameter('categoryRoute', self::getCategoryRoute($entity))
            ->set('category_route_name', ':categoryRouteName')->setParameter('categoryRouteName', self::getCategoryRoute($entity, true))
            ->where('id=:id')->setParameter('id', $entity->get('id'))
            ->executeQuery();

        if (!$entity->isNew()) {
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $this
                    ->getConnection()
                    ->createQueryBuilder()
                    ->update('category')
                    ->set('category_route', ':categoryRoute')->setParameter('categoryRoute', self::getCategoryRoute($child))
                    ->set('category_route_name', ':categoryRouteName')->setParameter('categoryRouteName', self::getCategoryRoute($child, true))
                    ->where('id=:id')->setParameter('id', $child->get('id'))
                    ->executeQuery();
            }
        }
    }
}
