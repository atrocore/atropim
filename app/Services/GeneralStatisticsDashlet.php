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

class GeneralStatisticsDashlet extends AbstractDashletService
{
    public function getDashlet(): array
    {
        $result['list'] = [
            [
                'id'     => 'product',
                'name'   => 'product',
                'amount' => $this->getAmountForScope('Product')
            ],
            [
                'id'     => 'category',
                'name'   => 'category',
                'amount' => $this->getAmountForScope('Category')
            ],
            [
                'id'     => 'productFamily',
                'name'   => 'productFamily',
                'amount' => $this->getAmountForScope('ProductFamily')
            ],
            [
                'id'     => 'attribute',
                'name'   => 'attribute',
                'amount' => $this->getAmountForScope('Attribute')
            ],
            [
                'id'     => 'productWithoutAssociatedProduct',
                'name'   => 'productWithoutAssociatedProduct',
                'amount' => $this->getAmountProductWithoutAssociatedProduct()
            ],
            [
                'id'     => 'productWithoutCategory',
                'name'   => 'productWithoutCategory',
                'amount' => $this->getAmountProductWithoutCategory()
            ],
            [
                'id'     => 'productWithoutAttribute',
                'name'   => 'productWithoutAttribute',
                'amount' => $this->getAmountProductWithoutAttribute()
            ]
        ];

        if (!empty($this->getInjection('metadata')->get('entityDefs.Product.fields.image'))) {
            $result['list'][] = [
                'id'     => 'productWithoutAssets',
                'name'   => 'productWithoutAssets',
                'amount' => $this->getAmountProductWithoutAssets()
            ];
        }

        $result['total'] = count($result['list']);

        return $result;
    }

    public function getQueryProductWithoutAssets($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql
            = "SELECT " . $select . " 
                FROM product as p 
                WHERE p.id NOT IN (SELECT product_id FROM product_asset WHERE deleted=0) 
                  AND p.deleted=0";

        return $sql;
    }

    /**
     * Get query for Product without AssociatedProduct
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutAssociatedProduct($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql = "SELECT " . $select . " 
                FROM product as p 
                WHERE 
                    (SELECT COUNT(ap.id)                  
                    FROM associated_product AS ap
                      JOIN product AS p_rel 
                        ON p_rel.id = ap.related_product_id AND p_rel.deleted = 0
                      JOIN product AS p_main 
                        ON p_main.id = ap.related_product_id AND p_main.deleted = 0
                      JOIN association 
                        ON association.id = ap.association_id AND association.deleted = 0
                    WHERE ap.deleted = 0 AND  ap.main_product_id = p.id) = 0 
                AND p.deleted = 0";

        return $sql;
    }

    /**
     * Get query for Product without Category
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutCategory($count = false): string
    {
        // prepare select
        $select = $count ? 'COUNT(p.id)' : 'p.id as id';

        return "SELECT $select 
                FROM product p 
                LEFT JOIN product_category pc ON pc.product_id=p.id AND pc.deleted=0
                WHERE p.deleted=0 
                  AND pc.id IS NULL";
    }

    /**
     * @return string
     */
    protected function getQueryProductWithoutAttribute(): string
    {
        return "SELECT COUNT(p.id)
                FROM product p
                LEFT JOIN product_attribute_value pav ON pav.product_id = p.id AND pav.deleted = 0
                WHERE p.deleted = 0 AND pav.id IS NULL";
    }

    protected function getAmountProductWithoutAssets(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $ids = $this
            ->getPDO()
            ->query($this->getQueryProductWithoutAssets(false))
            ->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * Get Amount Product without AssociatedProduct
     *
     * @return int
     */
    protected function getAmountProductWithoutAssociatedProduct(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $ids = $this
            ->getPDO()
            ->query($this->getQueryProductWithoutAssociatedProduct(false))
            ->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * Get amount Product without category
     *
     * @return int
     */
    protected function getAmountProductWithoutCategory(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $ids = $this
            ->getPDO()
            ->query($this->getQueryProductWithoutCategory(false))
            ->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * @return int
     */
    protected function getAmountProductWithoutAttribute(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $ids = $this
            ->getPDO()
            ->query($this->getQueryProductWithoutAttribute(false))
            ->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    protected function getAmountForScope(string $entityName): int
    {
        if (!$this->getInjection('acl')->check($entityName, 'read')) {
            return 0;
        }

        $result = $this->getInjection('serviceFactory')->create($entityName)->findEntities(['maxSize' => 1]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('acl');
    }
}
