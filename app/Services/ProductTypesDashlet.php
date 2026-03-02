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

namespace Pim\Services;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;

class ProductTypesDashlet extends AbstractDashletService
{
    /**
     * Get Product types
     *
     * @return array
     */
    public function getDashlet(): array
    {
        $types = [
            'nonHierarchicalProducts',
            'productHierarchies',
            'lowestLevelProducts'
        ];

        if (in_array('productBundles', $this->getInjection('metadata')->get('scopes.Product.mandatoryUnInheritedFields', []))) {
            $types[] = 'bundleProducts';
            $types[] = 'bundledProducts';
        }

        foreach ($types as $type) {
            $method = 'get' . ucfirst($type) . 'Query';
            if (method_exists($this, $method)) {
                $data = array_column($this->$method()->fetchAllAssociative(), 'total', 'is_active');
                $list[] = [
                    'id'        => $type,
                    'name'      => $this->getInjection('language')->translate($type),
                    'total'     => array_sum($data),
                    'active'    => isset($data[1]) ? (int)$data[1] : 0,
                    'notActive' => isset($data[0]) ? (int)$data[0] : 0,
                ];
            }
        }

        return ['total' => count($list), 'list' => $list];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    protected function getNonHierarchicalProductsQuery(): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('p.is_active, COUNT(p.id) AS total')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->andWhere("p.id NOT IN (SELECT ph.entity_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->andWhere("p.id NOT IN (SELECT ph.parent_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.is_active');
    }

    protected function getProductHierarchiesQuery(): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('p.is_active, COUNT(p.id) AS total')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->andWhere("p.id NOT IN (SELECT ph.entity_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->andWhere("p.id IN (SELECT ph.parent_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.is_active');
    }

    protected function getLowestLevelProductsQuery(): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('p.is_active, COUNT(p.id) AS total')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->andWhere("p.id IN (SELECT ph.entity_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->andWhere("p.id NOT IN (SELECT ph.parent_id FROM {$connection->quoteIdentifier('product_hierarchy')} ph WHERE ph.deleted=:false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.is_active');
    }

    protected function getBundleProductsQuery(): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('p.is_active, COUNT(p.id) AS total')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->andWhere("p.id IN (SELECT pb.product_bundle_id FROM {$connection->quoteIdentifier('product_bundle')} pb WHERE pb.deleted = :false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.is_active');
    }

    protected function getBundledProductsQuery(): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('p.is_active, COUNT(p.id) AS total')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->andWhere("p.id IN (SELECT pb.product_bundle_item_id FROM {$connection->quoteIdentifier('product_bundle')} pb WHERE pb.deleted = :false)")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.is_active');
    }
}
