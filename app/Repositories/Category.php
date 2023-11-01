<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Category extends Hierarchy
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
        $records = $this->getConnection()->createQueryBuilder()
            ->select('cc.channel_id')
            ->from($this->getConnection()->quoteIdentifier('category_channel'), 'cc')
            ->where('cc.deleted = :false')
            ->andWhere("cc.category_id IN (SELECT c.category_parent_id FROM {$this->getConnection()->quoteIdentifier('category')} c WHERE c.deleted = :false AND id = :id)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('id', $categoryId, Mapper::getParameterType($categoryId))
            ->fetchAllAssociative();

        return array_column($records, 'channel_id');
    }

    public function getNotRelatedWithCatalogsTreeIds(): array
    {
        $records = $this->getConnection()->createQueryBuilder()
            ->select('c.id')
            ->from($this->getConnection()->quoteIdentifier('category'), 'c')
            ->where('c.deleted = :false')
            ->andWhere('c.category_parent_id IS NULL')
            ->andWhere('c.id NOT IN (SELECT cc.category_id FROM catalog_category cc WHERE cc.deleted = :false)')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($records, 'id');
    }

    public function canUnRelateCatalog(Entity $category, string $catalogId): void
    {
        if (!$this->getEntityManager()->getRepository('Catalog')->hasProducts($catalogId)) {
            return;
        }

        $categoriesIds = array_column($category->getChildren()->toArray(), 'id');
        $categoriesIds[] = $category->get('id');

        $records = $this->getConnection()->createQueryBuilder()
            ->select('pc.id')
            ->from('product_category', 'pc')
            ->where('pc.deleted = :false')
            ->andWhere('pc.category_id IN (:categoryIds)')
            ->andWhere("pc.product_id IN (SELECT p.id FROM {$this->getConnection()->quoteIdentifier('product')} p WHERE p.catalog_id = :catalogId AND p.deleted = :false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('categoryIds', $categoriesIds, Mapper::getParameterType($categoriesIds))
            ->setParameter('catalogId', $catalogId, Mapper::getParameterType($catalogId))
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->fetchAllAssociative();

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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = $this->getMapper()->addRelation($category, 'catalogs', $catalogId);
            foreach ($category->getChildren() as $child) {
                $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $child->get('id'), 'catalogs', $catalogId);
            }
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = $this->getMapper()->removeRelation($category, 'catalogs', $catalogId);

            foreach ($category->getChildren() as $child) {
                $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $child->get('id'), 'catalogs', $catalogId);

                $this->getConnection()->createQueryBuilder()
                    ->delete('product_category')
                    ->where('category_id = :childId')
                    ->andWhere('product_id IN (SELECT id FROM product WHERE catalog_id = :catalogId)')
                    ->setParameter('childId', $child->get('id'))
                    ->setParameter('catalogId', $catalogId)
                    ->executeQuery();
            }

            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = $this->getMapper()->addRelation($category, 'channels', $channelId);

            if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
                foreach ($category->getChildren() as $child) {
                    $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $child->get('id'), 'channels', $channelId);
                }
            }
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {

            $result = $this->getMapper()->removeRelation($category, 'channels', $channelId);
            if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
                foreach ($category->getChildren() as $child) {
                    $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $child->get('id'), 'channels', $channelId);
                }
            }

            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = $this->getMapper()->addRelation($category, 'products', $product->get('id'));
            $this->getProductRepository()->updateProductCategorySortOrder($product, $category);
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
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

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = $this->getMapper()->removeRelation($category, 'products', $product->get('id'));
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    public function getEntityPosition(Entity $entity, string $parentId): ?int
    {
        $sortBy = Util::toUnderScore($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'name'));
        $sortOrder = !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC';

        if (empty($parentId)) {
            $additionalWhere = ' AND t.category_parent_id IS NULL ';
        } else {
            $additionalWhere = ' AND t.category_parent_id=:parentId';
        }

        if (Converter::isPgSQL($this->getConnection())) {
            $query = "SELECT x.position
                      FROM (SELECT t.id, row_number() over(ORDER BY t.sort_order ASC, t.$sortBy $sortOrder, t.id ASC) AS position
                        FROM {$this->getConnection()->quoteIdentifier('category')} t
                        WHERE t.deleted=:false $additionalWhere) x
                      WHERE x.id=:id";
        } else {
            $query = "SELECT x.position
                      FROM (SELECT t.id, @rownum:=@rownum + 1 AS position
                        FROM {$this->getConnection()->quoteIdentifier('category')} t
                            JOIN (SELECT @rownum:=0) r
                        WHERE t.deleted=:false $additionalWhere
                        ORDER BY t.sort_order ASC, t.$sortBy $sortOrder, t.id ASC) x
                      WHERE x.id=:id";
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':id', $entity->get('id'));
        if (!empty($parentId)) {
            $sth->bindValue(':parentId', $parentId);
        }
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $position = $sth->fetch(\PDO::FETCH_COLUMN);

        return (int)$position;
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
    {
        $sortBy = Util::toUnderScore($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'name'));
        $sortOrder = !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC';

        $select = ['c.*'];
        if ($withChildrenCount) {
            $select [] = "(SELECT COUNT(c1.id) FROM {$this->getConnection()->quoteIdentifier('category')} c1 WHERE c1.category_parent_id=c.id AND c1.deleted=:false) as children_count";
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select($select)
            ->from($this->getConnection()->quoteIdentifier('category'), 'c')
            ->where('c.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->addOrderBy('c.sort_order', 'ASC')
            ->addOrderBy("c.$sortBy", $sortOrder)
            ->addOrderBy('c.id', 'ASC');

        if (empty($parentId)) {
            $qb->andWhere('c.category_parent_id IS NULL');
        } else {
            $qb->andWhere('c.category_parent_id = :parentId');
            $qb->setParameter('parentId', $parentId);
        }

        if (!is_null($offset) && !is_null($maxSize)) {
            $qb->setFirstResult($offset);
            $qb->setMaxResults($maxSize);
        }

        return Util::arrayKeysToCamelCase($qb->fetchAllAssociative());
    }

    public function getChildrenCount(string $parentId, $selectParams = null): int
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->select('COUNT(id) as count')
            ->from($this->getConnection()->quoteIdentifier('category'), 'c')
            ->where('c.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false));

        if (empty($parentId)) {
            $qb->andWhere('c.category_parent_id IS NULL');
        } else {
            $qb->andWhere('c.category_parent_id = :parentId');
            $qb->setParameter('parentId', $parentId);
        }

        $res = $qb->fetchAssociative();

        return empty($res) ? 0 : (int)$res['count'];
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
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

        if ($entity->isAttributeChanged('categoryParentId') && !empty($parent = $this->get($entity->get('categoryParentId')))) {
            $entity->set('catalogsIds', $parent->getLinkMultipleIdList('catalogs'));
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

        $this->getConnection()->createQueryBuilder()
            ->delete('product_category')
            ->where('category_id = :categoryId')
            ->setParameter('categoryId', $entity->get('id'))
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->delete('category_channel')
            ->where('category_id = :categoryId')
            ->setParameter('categoryId', $entity->get('id'))
            ->executeQuery();

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

        foreach ($data as $id) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('category'), 'c')
                ->set('c.sort_order', ':sortOrder')
                ->where('c.id = :id')
                ->setParameter('sortOrder', $sortOrder, Mapper::getParameterType($sortOrder))
                ->setParameter('id', $id)
                ->executeQuery();

            // increase sort order
            $sortOrder = $sortOrder + 10;
        }
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

    protected function updateRoute(Entity $entity): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('category'), 'c')
            ->set('category_route', ':categoryRoute')
            ->set('category_route_name', ':categoryRouteName')
            ->where('c.id = :id')
            ->setParameter('categoryRoute', self::getCategoryRoute($entity))
            ->setParameter('categoryRouteName', self::getCategoryRoute($entity, true))
            ->setParameter('id', $entity->get('id'))
            ->executeQuery();
    }

    protected function updateCategoryTree(Entity $entity): void
    {
        if (!empty($entity->recursiveSave)) {
            return;
        }

        $this->updateRoute($entity);

        if (!$entity->isNew()) {
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $this->updateRoute($child);
            }
        }
    }
}
