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

/**
 * Class ProductTypesDashlet
 */
class ProductTypesDashlet extends AbstractProductDashletService
{
    /**
     * Get Product types
     *
     * @return array
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];
        $productData = [];

        // get product data form DB
        $sql = "SELECT
                    type      AS type,
                    is_active AS isActive,
                    COUNT(id) AS amount
                FROM product
                WHERE deleted = 0 AND type IN " . $this->getProductTypesCondition() . "
                GROUP BY is_active, type;";

        $sth = $this->getPDO()->prepare($sql);
        $sth->execute();
        $products = $sth->fetchAll(\PDO::FETCH_ASSOC);

        // prepare product data
        foreach ($products as $product) {
            if ($product['isActive']) {
                $productData[$product['type']]['active'] = $product['amount'];
            } else {
                $productData[$product['type']]['notActive'] = $product['amount'];
            }
        }

        // prepare result
        foreach ($productData as $type => $value) {
            $value['active'] = $value['active'] ?? 0;
            $value['notActive'] = $value['notActive'] ?? 0;

            $result['list'][] = [
                'id'        => $type,
                'name'      => $type,
                'total'     => $value['active'] + $value['notActive'],
                'active'    => (int)$value['active'],
                'notActive' => (int)$value['notActive']
            ];
        }


        $result['total'] = count($result['list']);

        return $result;
    }
}
