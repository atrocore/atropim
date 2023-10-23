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

use Atro\ORM\DB\RDB\Mapper;

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
        $result = [
            'total' => 0,
            'list'  => []
        ];

        $connection = $this->getEntityManager()->getConnection();

        $data = $connection->createQueryBuilder()
            ->select([
                "c.id",
                "c.name",
                "(SELECT COUNT(p.id) AS total FROM {$connection->quoteIdentifier('product')} p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=:true) AS total_active",
                "(SELECT COUNT(p.id) AS total FROM {$connection->quoteIdentifier('product')} p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=0 AND p.is_active=:false) AS total_inactive"
            ])
            ->from($connection->quoteIdentifier('channel'), 'c')
            ->where('c.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->fetchAllAssociative();

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
