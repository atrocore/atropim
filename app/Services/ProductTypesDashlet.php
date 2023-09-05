<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

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

        if (in_array('productBundles', $this->getInjection('metadata')->get('scopes.Product.mandatoryUnInheritedRelations', []))) {
            $types[] = 'bundleProducts';
            $types[] = 'bundledProducts';
        }

        foreach ($types as $type) {
            $method = 'get' . ucfirst($type) . 'Query';
            if (method_exists($this, $method)) {
                $data = array_column($this->getPDO()->query($this->$method())->fetchAll(\PDO::FETCH_ASSOC), 'total', 'is_active');
                $list[] = [
                    'id'        => $type,
                    'name'      => $this->getInjection('language')->translate($type),
                    'total'     => array_sum($data),
                    'active'    => $data[1] ? (int)$data[1] : 0,
                    'notActive' => $data[0] ? (int)$data[0] : 0,
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

    protected function getNonHierarchicalProductsQuery(): string
    {
        return "SELECT is_active, COUNT(id) AS total
                 FROM `product`
                 WHERE deleted=0
                   AND id NOT IN (SELECT entity_id FROM `product_hierarchy` WHERE deleted=0)
                   AND id NOT IN (SELECT parent_id FROM `product_hierarchy` WHERE deleted=0)
                 GROUP BY is_active";
    }

    protected function getProductHierarchiesQuery(): string
    {
        return "SELECT is_active, COUNT(id) AS total
                 FROM `product`
                 WHERE deleted=0
                     AND id NOT IN (SELECT entity_id FROM `product_hierarchy` WHERE deleted=0)
                     AND id IN (SELECT parent_id FROM `product_hierarchy` WHERE deleted=0)
                 GROUP BY is_active";
    }

    protected function getLowestLevelProductsQuery(): string
    {
        return "SELECT is_active, COUNT(id) AS total
                 FROM `product`
                 WHERE deleted=0
                     AND id IN (SELECT entity_id FROM `product_hierarchy` WHERE deleted=0)
                     AND id NOT IN (SELECT parent_id FROM `product_hierarchy` WHERE deleted=0)
                 GROUP BY is_active";
    }

    protected function getBundleProductsQuery(): string
    {
        return "SELECT is_active, COUNT(id) AS total
                 FROM `product`
                 WHERE deleted=0
                     AND id IN (SELECT product_bundle_id FROM `product_bundle` WHERE deleted=0)
                 GROUP BY is_active";
    }

    protected function getBundledProductsQuery(): string
    {
        return "SELECT is_active, COUNT(id) AS total
                 FROM `product`
                 WHERE deleted=0
                     AND id IN (SELECT product_bundle_item_id FROM `product_bundle` WHERE deleted=0)
                 GROUP BY is_active";
    }
}
