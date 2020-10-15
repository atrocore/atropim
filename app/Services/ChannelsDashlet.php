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
 * ChannelsDashlet class
 */
class ChannelsDashlet extends AbstractDashletService
{
    /**
     * Get general statistic
     *
     * @return array
     */
    public function getDashlet(): array
    {
        // prepare result
        $result = [
            'total' => 0,
            'list'  => []
        ];

        // prepare sql
        $sql = "SELECT
                       c.id,
                       c.name,
                       (SELECT COUNT(p.id) AS total FROM product p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=1) AS totalActive,
                       (SELECT COUNT(p.id) AS total FROM product p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=0) AS totalInactive
                FROM channel AS c
                WHERE c.deleted=0";

        // get data
        $data = $this->getEntityManager()->nativeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            foreach ($data as $row) {
                $result['list'][] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'products'  => (int)$row['totalActive'] + (int)$row['totalInactive'],
                    'active'    => (int)$row['totalActive'],
                    'notActive' => (int)$row['totalInactive']
                ];
            }

            $result['total'] = count($result['list']);
        }

        return $result;
    }
}
