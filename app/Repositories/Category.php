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
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Category extends Hierarchy
{
    public function getCategoryRoute(Entity $entity, bool $isName = false): string
    {
        // prepare result
        $result = '';

        // prepare data
        $data = [];
        $parents = $this->getParents($entity);

        while (!empty($parents[0])) {
            // push id
            $parent = $parents->offsetGet(0);
            if (!$isName || empty($parent->get('name'))) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim((string)$parent->get('name'));
            }

            // to next category
            $parents = $this->getParents($parent);
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

    protected function getParents(Entity $entity): ?EntityCollection
    {
        $parents = $entity->get('parents');
        if (empty($parents[0]) && !empty($entity->get('parentsIds'))) {
            $parents = $this->where(['id' => $entity->get('parentsIds')])->find();
        }

        return $parents;
    }

    public function getParentChannelsIds(string $categoryId): array
    {
        $records = $this->getConnection()->createQueryBuilder()
            ->select('cc.channel_id')
            ->from($this->getConnection()->quoteIdentifier('category_channel'), 'cc')
            ->where('cc.deleted = :false')
            ->andWhere("cc.category_id IN (SELECT c.parent_id FROM {$this->getConnection()->quoteIdentifier('category_hierarchy')} c WHERE c.deleted = :false AND entity_id = :id)")
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
            ->andWhere("c.id NOT IN (SELECT DISTINCT c.entity_id FROM {$this->getConnection()->quoteIdentifier('category_hierarchy')} c WHERE c.deleted = :false)")
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

        $categoriesIds = $this->getChildrenRecursivelyArray($category->get('id'));
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

        if (!$this->isRoot($category->get('id'))) {
            throw new BadRequest($this->exception('onlyRootCategoryCanBeLinked'));
        }

        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = $this->getMapper()->addRelation($category, 'catalogs', $catalogId);
            foreach ($this->getChildrenRecursivelyArray($category->get('id')) as $childId) {
                $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $childId, 'catalogs', $catalogId);
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

        if (!$this->isRoot($category->get('id'))) {
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

            foreach ($this->getChildrenRecursivelyArray($category->get('id')) as $childId) {
                $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $childId, 'catalogs', $catalogId);

                $this->getConnection()->createQueryBuilder()
                    ->delete('product_category')
                    ->where('category_id = :childId')
                    ->andWhere('product_id IN (SELECT id FROM product WHERE catalog_id = :catalogId)')
                    ->setParameter('childId', $childId)
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
                foreach ($this->getChildrenRecursivelyArray($category->get('id')) as $childId) {
                    $options['pseudoTransactionManager']->pushLinkEntityJob('Category', $childId, 'channels', $channelId);
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
                foreach ($this->getChildrenRecursivelyArray($category->get('id')) as $childId) {
                    $options['pseudoTransactionManager']->pushUnLinkEntityJob('Category', $childId, 'channels', $channelId);
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

    /**
     * @param Entity $entity
     * @param array $options
     *
     * @throws BadRequest
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isAttributeChanged('parentsIds')) {
            $parents = $entity->get('parents');

            if (!empty($parents) && count($parents) > 0) {
                if (!$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
                    foreach ($parents as $parent) {
                        $categoryParentProducts = $parent->get('products');
                        if (!empty($categoryParentProducts) && count($categoryParentProducts) > 0) {
                            throw new BadRequest($this->exception('parentCategoryHasProducts'));
                        }
                    }
                }

                $catalogIds = [];
                foreach ($parents as $parent) {
                    foreach ($parent->getLinkMultipleIdList('catalogs') as $catalogId) {
                        if (!in_array($catalogId, $catalogIds)) {
                            $catalogIds[] = $catalogId;
                        }
                    }
                }
                $entity->set('catalogsIds', $catalogIds);
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
        // build tree
        $this->updateCategoryTree($entity);

        // relate parent channels
        $parents = $entity->get('parents');
        if ($entity->isNew() && !empty($parents) && count($parents) > 0) {
            foreach ($parents as $parent) {
                if (!empty($parentChannels = $parent->get('channels')) && count($parentChannels) > 0) {
                    foreach ($parentChannels as $parentChannel) {
                        $this->relate($entity, 'channels', $parentChannel);
                    }
                }
            }
        }

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') !== 'cascade') {
            if (!empty($products = $entity->get('products')) && count($products) > 0) {
                throw new BadRequest($this->exception("categoryHasProducts"));
            }

            if (!empty($categories = $entity->get('categories')) && count($categories) > 0) {
                throw new BadRequest($this->exception("categoryHasChildCategoryAndCantBeDeleted"));
            }
        }

    }

    public function remove(Entity $entity, array $options = [])
    {
        $result = parent::remove($entity);

        $this->getEntityManager()->getRepository('ProductCategory')
            ->where(["categoryId"  => $entity->get('id')])
            ->removeCollection();

        $this->getEntityManager()->getRepository('CategoryChannel')
            ->where(["categoryId"  => $entity->get('id')])
            ->removeCollection();

        return $result;
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()->createQueryBuilder()
            ->update('product_category')
            ->set('deleted',':deleted')
            ->where('category_id = :categoryId')
            ->setParameter('categoryId', $entity->get('id'))
            ->setParameter('deleted',false, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('category_channel')
            ->set('deleted',':deleted')
            ->where('category_id = :categoryId')
            ->setParameter('categoryId', $entity->get('id'))
            ->setParameter('deleted',false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
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
            $ids = $this->getParentsRecursivelyArray($entity->get('id'));
            foreach ($ids as $id) {
                $parent = $this->get($id);
                if (!empty($parent)) {
                    $parent->set('isActive', true);
                    $this->saveEntity($parent);
                }
            }
        }
    }

    protected function deactivateChildren(Entity $entity): void
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->recursiveSave) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $ids = $this->getChildrenRecursivelyArray($entity->get('id'));
            foreach ($ids as $id) {
                $child = $this->get($id);
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

    protected function updateRoute(Entity $entity): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('category'), 'c')
            ->set('category_route', ':categoryRoute')
            ->set('category_route_name', ':categoryRouteName')
            ->where('c.id = :id')
            ->setParameter('categoryRoute', $this->getCategoryRoute($entity))
            ->setParameter('categoryRouteName', $this->getCategoryRoute($entity, true))
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
            $ids = $this->getChildrenRecursivelyArray($entity->get('id'));
            foreach ($ids as $id) {
                $child = $this->get($id);
                $this->updateRoute($child);
            }
        }
    }
}
