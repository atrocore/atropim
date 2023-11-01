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

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class Category extends AbstractSelectManager
{
    public function addChildrenCount(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper)
    {
        if (!empty($params['aggregation'])) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $queryConverter = $mapper->getQueryConverter();

        $tableAlias = $queryConverter->getMainTableAlias();
        $fieldAlias = $queryConverter->fieldToAlias('childrenCount');

        $qb->add(
            'select',
            ["(SELECT COUNT(c1.id) FROM {$connection->quoteIdentifier('category')}  AS c1 WHERE c1.category_parent_id={$tableAlias}.id AND c1.deleted=:false) as $fieldAlias"], true
        );
        $qb->setParameter('false', false, Mapper::getParameterType(false));
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'addChildrenCount'];
    }

    protected function boolFilterNotParents(&$result): void
    {
        $notParents = (string)$this->getSelectCondition('notParents');
        if (empty($notParents)) {
            return;
        }

        $category = $this->getEntityManager()->getRepository('Category')->get($notParents);
        if (!empty($category)) {
            $result['whereClause'][] = [
                'id!=' => array_merge($category->getParentsIds(), [$category->get('id')])
            ];
        }
    }

    protected function boolFilterNotChildren(&$result): void
    {
        $notChildren = (string)$this->getSelectCondition('notChildren');
        if (empty($notChildren)) {
            return;
        }

        $category = $this->getEntityManager()->getRepository('Category')->get($notChildren);
        if (!empty($category)) {
            $result['whereClause'][] = [
                'id!=' => array_merge(array_column($category->getChildren()->toArray(), 'id'), [$category->get('id')])
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyRootCategory(array &$result)
    {
        if ($this->hasBoolFilter('onlyRootCategory')) {
            $result['whereClause'][] = [
                'categoryParentId' => null
            ];
        }
    }

    /**
     * @param array $result
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterOnlyCatalogCategories(array &$result)
    {
        $catalogId = $this->getSelectCondition('onlyCatalogCategories');

        if ($catalogId === false) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        if (empty($catalogId)) {
            $rows = $connection->createQueryBuilder()
                ->select('category_id')
                ->from('catalog_category')
                ->where('deleted = :false')
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->fetchAllAssociative();

            $result['whereClause'][] = [
                'id!=' => array_column($rows, 'category_id')
            ];
            return;
        }

        $rows = $connection->createQueryBuilder()
            ->select('category_id')
            ->from('catalog_category')
            ->where('deleted = :false')
            ->andWhere('catalog_id = :catalogId')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('catalogId', $catalogId)
            ->fetchAllAssociative();

        $result['whereClause'][] = [
            'id' => array_column($rows, 'category_id')
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyLeafCategories(array &$result)
    {
        if (!$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            $parents = $this
                ->getEntityManager()
                ->getRepository('Category')->select(['categoryParentId'])->where(['categoryParentId!=' => null])
                ->find()
                ->toArray();

            if (!empty($parents)) {
                $result['whereClause'][] = [
                    'id!=' => array_unique(array_column($parents, 'categoryParentId'))
                ];
            }
        }
    }

    /**
     * @param $result
     *
     * @return void
     */
    protected function boolFilterLinkedWithProduct(&$result)
    {
        if ($this->hasBoolFilter('linkedWithProduct')) {
            $list = $this
                ->getEntityManager()
                ->getRepository('Category')
                ->select(['id', 'categoryRoute'])
                ->join('products')
                ->find()
                ->toArray();

            if ($list) {
                $ids = [];

                foreach ($list as $category) {
                    $ids[] = $category['id'];

                    $parentCategoriesIds = explode("|", trim($category['categoryRoute'], "|"));
                    $ids = array_merge($ids, $parentCategoriesIds);
                }

                $result['whereClause']['id'] = array_unique($ids);
            } else {
                $result['whereClause']['id'] = null;
            }
        }
    }
}
