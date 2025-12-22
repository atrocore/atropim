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

namespace Pim\SelectManagers;

use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class Product extends AbstractSelectManager
{
    private array $filterByCategories = [];

    protected function textFilter($textFilter, &$result)
    {
        parent::textFilter($textFilter, $result);

        if (empty($result['whereClause'])) {
            return;
        }

        $last = array_pop($result['whereClause']);

        if (!isset($last['OR'])) {
            return;
        }

        $result['whereClause'][] = $last;
    }

    /**
     * NotLinkedWithBrand filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithBrand(array &$result)
    {
        // prepare data
        $brandId = (string)$this->getSelectCondition('notLinkedWithBrand');

        if (!empty($brandId)) {
            $products = $this->getEntityManager()->getRepository($this->entityType)
                ->select(['id'])
                ->where(['brandId' => $brandId])
                ->find();

            $result['whereClause'][] = [
                'id!=' => array_column($products->toArray(), 'id')
            ];
        }
    }

    protected function getProductsIdsByClassificationIds(array $classificationIds): array
    {
        $products = $this
            ->getEntityManager()
            ->getRepository($this->entityType)
            ->join('classifications')
            ->select(['id'])
            ->where(['classifications.id' => $classificationIds])
            ->find();

        return array_column($products->toArray(), 'id');
    }

    protected function fetchAll(string $query): array
    {
        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        if (str_contains($query, ':zero')) {
            $sth->bindValue(':zero', 0, \PDO::PARAM_INT);
        }
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }


    protected function boolFilterLinkedWithCategory(array &$result)
    {
        $result['callbacks'][] = [$this, 'applyBoolFilterLinkedWithCategory'];
    }

    public function applyBoolFilterLinkedWithCategory(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $id = $this->getSelectCondition('linkedWithCategory');
        if (empty($id)) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository('Category');
        if (empty($category = $repository->get($id))) {
            throw new BadRequest('No such category');
        }

        // collect categories
        $categoriesIds = $repository->getChildrenRecursivelyArray($category->get('id'));
        $categoriesIds[] = $category->get('id');

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $relTable = Util::toUnderScore(lcfirst($this->entityType).'Category');
        $idColumn = Util::toUnderScore(lcfirst($this->entityType).'Id');

        $qb->andWhere("{$tableAlias}.id IN (SELECT $idColumn FROM $relTable WHERE $idColumn IS NOT NULL AND deleted=:false AND category_id IN (:categoriesIds))");
        $qb->setParameter('false', false, Mapper::getParameterType(false));
        $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
    }

    protected function boolFilterWithoutMainImage(&$result)
    {
        $connection = $this->getEntityManager()->getConnection();

        $relTable = Util::toUnderScore(lcfirst($this->entityType).'File');
        $idColumn = Util::toUnderScore(lcfirst($this->entityType).'Id');

        $res = $connection->createQueryBuilder()
            ->select('p.id')
            ->from($connection->quoteIdentifier(Util::toUnderScore(lcfirst($this->entityType))), 'p')
            ->where("p.id NOT IN (SELECT DISTINCT pa.$idColumn FROM $relTable pa WHERE pa.is_main_image = :true)")
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $result['whereClause'][] = [
            'id' => array_column($res, 'id')
        ];
    }

    protected function prepareFilterByCategories(array &$params): void
    {
        if (empty($params['where'])) {
            return;
        }

        $this->filterByCategories = [];

        foreach ($params['where'] as $k => $row) {
            if (empty($row['attribute'])) {
                continue;
            }
            if ($row['attribute'] == 'categories' && empty($row['subQuery'])) {
                if (!empty($row['value'])) {
                    $this->filterByCategories['ids'] = array_merge($this->filterByCategories, $row['value']);
                    $this->filterByCategories['row'] = $row;
                    unset($params['where'][$k]);
                }
            }
        }

        $params['where'] = array_values($params['where']);
    }

    public function applyFilteringByCategories(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (empty($this->filterByCategories['ids'])) {
            return;
        }

        $ids = $this->filterByCategories['ids'];
        $row = $this->filterByCategories['row'];

        $connection = $this->getEntityManager()->getConnection();

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $relTable = Util::toUnderScore(lcfirst($this->entityType).'Category');
        $idColumn = Util::toUnderScore(lcfirst($this->entityType).'Id');

        $categoriesIds = [];
        if (in_array($row['type'], ['linkedWith', 'notLinkedWith'])) {
            foreach ($ids as $id) {
                $res = $connection->createQueryBuilder()
                    ->select('c.id')
                    ->from($connection->quoteIdentifier('category'), 'c')
                    ->where('c.id= :id OR c.routes LIKE :idLike')
                    ->andWhere('c.deleted = :false')
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->setParameter('id', $id)
                    ->setParameter('idLike', "%|{$id}|%")
                    ->fetchAllAssociative();
                $categoriesIds = array_merge($categoriesIds, array_column($res, 'id'));
            }
        }

        switch ($row['type']) {
            case 'isNotLinked':
                $qb->andWhere("$tableAlias.id NOT IN (SELECT pc44.$idColumn FROM $relTable pc44 WHERE pc44.deleted=:false)");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                break;
            case 'isLinked':
                $qb->andWhere("$tableAlias.id IN (SELECT pc44.$idColumn FROM $relTable pc44 WHERE pc44.deleted=:false)");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                break;
            case 'linkedWith':
                $qb->andWhere("$tableAlias.id IN (SELECT pc22.$idColumn FROM $relTable pc22 WHERE pc22.deleted=:false AND pc22.category_id IN (:categoriesIds))");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
                break;
            case 'notLinkedWith':
                $qb->andWhere("$tableAlias.id NOT IN (SELECT pc22.$idColumn FROM $relTable pc22 WHERE pc22.deleted=:false AND pc22.category_id IN (:categoriesIds))");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
                break;
        }
    }
}
