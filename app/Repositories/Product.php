<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\Core\EventManager\Event;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Product extends Hierarchy
{
    public function getProductsIdsViaAccountId(string $accountId): array
    {
        $idColumn = $this->getTableName() . '_id';
        $res = $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->distinct()
            ->from($this->getConnection()->quoteIdentifier($this->getTableName() . '_channel'), 'pc')
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier($this->getTableName()), 'p', "pc.$idColumn=p.id AND p.deleted=:false")
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier('channel'), 'c', 'pc.channel_id=c.id AND c.deleted=:false')
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier('account'), 'a', 'a.channel_id=c.id AND a.deleted=:false')
            ->where('pc.deleted=:false')
            ->andWhere('a.id=:id')
            ->setParameter('id', $accountId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }

    public function getCategoriesChannelsIds(string $productId): array
    {
        $idColumn = $this->getTableName() . '_id';
        $res = $this->getConnection()->createQueryBuilder()
            ->select('cc.channel_id')
            ->from($this->getConnection()->quoteIdentifier('category_channel'), 'cc')
            ->where('cc.deleted=:false')
            ->andWhere(
                "cc.category_id IN (SELECT pc.category_id FROM {$this->getConnection()->quoteIdentifier($this->getTableName() . '_category')} pc WHERE pc.deleted=:false AND pc.$idColumn=:productId)"
            )
            ->setParameter('productId', $productId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($res, 'channel_id');
    }

    public function getInputLanguageList(): array
    {
        return $this->getConfig()->get('inputLanguageList', []);
    }


    public function unlinkProductsFromNonLeafCategories(): void
    {
        $idColumn = $this->getTableName() . '_id';
        $data = $this->getConnection()->createQueryBuilder()
            ->select("$idColumn, category_id")
            ->from($this->getTableName() . '_category')
            ->where('category_id IN (SELECT DISTINCT parent_id FROM category_hierarchy where deleted=:false)')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        foreach ($data as $row) {
            $product = $this->get($row[$idColumn]);
            $category = $this->getEntityManager()->getRepository('Category')->get($row['category_id']);
            if (!empty($product) && !empty($category)) {
                $this->unrelate($product, 'categories', $category);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        // prepare params
        $params = $this
            ->dispatch('ProductRepository', 'findRelated', new Event(['entity' => $entity, 'relationName' => $relationName, 'params' => $params]))
            ->getArgument('params');

        if ($relationName == 'categories' && !empty($params)) {
            if (isset($params['additionalColumns']['sorting'])) {
                unset($params['additionalColumns']['sorting']);
            }
        }

        return parent::findRelated($entity, $relationName, $params);
    }

    /**
     * Is product can linked with non-lead category
     *
     * @param Entity|string $category
     *
     * @return bool
     * @throws BadRequest
     */
    public function isProductCanLinkToNonLeafCategory(string $categoryId): bool
    {
        if ($this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            return true;
        }

        if ($this->getEntityManager()->getRepository('Category')->hasChildren($categoryId)) {
            throw new BadRequest($this->translate("productCanNotLinkToNonLeafCategory", 'exceptions', 'Product'));
        }

        return true;
    }

    public function getProductCategoryLinkData(array $productsIds, array $categoriesIds): array
    {
        $idColumn = $this->getTableName() . '_id';
        return $this->getConnection()->createQueryBuilder()
            ->select('pc.*')
            ->from($this->getConnection()->quoteIdentifier($this->getTableName() . '_category'), 'pc')
            ->where("pc.$idColumn IN (:productsIds)")
            ->andWhere('pc.category_id IN (:categoriesIds)')
            ->andWhere('pc.deleted = :false')
            ->setParameter('productsIds', $productsIds, Mapper::getParameterType($productsIds))
            ->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->orderBy('sorting', 'DESC')
            ->fetchAllAssociative();
    }

    /**
     * @param string|Entity $productId
     * @param string|Entity $categoryId
     * @param int|null      $sorting
     * @param bool          $cascadeUpdate
     */
    public function updateProductCategorySortOrder($productId, $categoryId, int $sorting = null, bool $cascadeUpdate = true): void
    {
        if ($productId instanceof Entity) {
            $productId = $productId->get('id');
        }
        if ($categoryId instanceof Entity) {
            $categoryId = $categoryId->get('id');
        }

        // get link data
        $linkData = $this->getProductCategoryLinkData([$productId], [$categoryId]);

        // get max
        $max = (int)$linkData[0]['sorting'];

        if (is_null($sorting)) {
            $sorting = $max + 10;
            $cascadeUpdate = false;
        }

        $idColumn = $this->getTableName() . '_id';
        $tableName = $this->getConnection()->quoteIdentifier($this->getTableName() . '_category');

        // update current
        $this->getConnection()->createQueryBuilder()
            ->update($tableName, 'pc')
            ->set('sorting', ':sorting')
            ->where('pc.category_id = :categoryId')
            ->andWhere("pc.$idColumn = :productId")
            ->andWhere('pc.deleted = :false')
            ->setParameter('sorting', $sorting, Mapper::getParameterType($sorting))
            ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
            ->setParameter('productId', $productId, Mapper::getParameterType($productId))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->executeQuery();

        if ($cascadeUpdate) {
            // get next records
            $res = $this->getConnection()->createQueryBuilder()
                ->select('pc.id')
                ->from($tableName, 'pc')
                ->where('pc.sorting >= :sorting')
                ->andWhere('pc.category_id = :categoryId')
                ->andWhere('pc.deleted = :false')
                ->andWhere("pc.$idColumn != :productId")
                ->setParameter('sorting', $sorting, Mapper::getParameterType($sorting))
                ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
                ->setParameter('productId', $productId, Mapper::getParameterType($productId))
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->orderBy('pc.sorting', 'ASC')
                ->fetchAllAssociative();

            $ids = array_column($res, 'id');

            // update next records
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $max = $max + 10;

                    $this->getConnection()->createQueryBuilder()
                        ->update($tableName, 'pc')
                        ->set('sorting', ':sorting')
                        ->where('pc.id = :id')
                        ->setParameter('sorting', $max, Mapper::getParameterType($max))
                        ->setParameter('id', $id, Mapper::getParameterType($id))
                        ->executeQuery();
                }
            }
        }
    }

    /**
     * @param string $categoryId
     * @param array  $ids
     *
     * @return void
     */
    public function updateSortOrderInCategory(string $categoryId, array $ids): void
    {
        $idColumn = $this->getTableName() . '_id';
        $tableName = $this->getTableName() . '_category';

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;

            $this->getConnection()->createQueryBuilder()
                ->update($tableName, 'pc')
                ->set('sorting', ':sorting')
                ->where("pc.$idColumn = :productId")
                ->andWhere('pc.category_id = :categoryId')
                ->andWhere('pc.deleted = :false')
                ->setParameter('sorting', $sortOrder, Mapper::getParameterType($sortOrder))
                ->setParameter('productId', $id, Mapper::getParameterType($id))
                ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->executeQuery();
        }
    }

    public function getTableName(): string
    {
        return Util::toUnderScore(lcfirst($this->entityName));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository($this->entityName . 'File')->removeByProductId($entity->get('id'));
        $this->getEntityManager()->getRepository($this->entityName . 'Channel')->where([lcfirst($this->entityName) . 'Id' => $entity->get('id')])->removeCollection();

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()
            ->createQueryBuilder()
            ->update('associated_' . $this->getTableName())
            ->set('deleted', ':false')
            ->where('associating_item_id = :productId')
            ->orWhere('associated_item_id = :productId')
            ->andWhere('deleted = :true')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('productId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();
    }

    public function getProductsHierarchyMap(array $productIds): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('t.entity_id, t.parent_id')
            ->from($this->getConnection()->quoteIdentifier($this->getTableName() . '_hierarchy'), 't')
            ->where('t.entity_id IN (:ids)')
            ->andWhere('t.deleted = :false')
            ->setParameter('ids', $productIds, Mapper::getParameterType($productIds))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        $result = [];
        foreach ($res as $row) {
            $result[] = [
                'childId'   => $row['entity_id'],
                'parent_id' => $row['parentId']
            ];
        }

        return $result;
    }

    protected function translate(string $key, string $label, $scope = ''): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function dispatch(string $target, string $action, Event $event): Event
    {
        return $this->getInjection('eventManager')->dispatch($target, $action, $event);
    }
}
