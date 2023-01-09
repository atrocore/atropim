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
