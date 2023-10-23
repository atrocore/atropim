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
                       (SELECT COUNT(p.id) AS total FROM product p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=:true) AS total_active,
                       (SELECT COUNT(p.id) AS total FROM product p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=:false) AS total_inactive
                FROM channel AS c
                WHERE c.deleted=:false";

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->bindValue(':true', true, \PDO::PARAM_BOOL);
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            foreach ($data as $row) {
                $result['list'][] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'products'  => (int)$row['total_active'] + (int)$row['total_inactive'],
                    'active'    => (int)$row['total_active'],
                    'notActive' => (int)$row['total_inactive']
                ];
            }

            $result['total'] = count($result['list']);
        }

        return $result;
    }
}
