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

namespace Pim\Dashlets;

use Atro\Core\Dashlets\DashletInterface;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;

/**
 * Counts the active and the inactive products of each channel.
 */
class Channels implements DashletInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getData(array $params): array
    {
        $productTable = $this->connection->quoteIdentifier('product');

        $data = $this->connection->createQueryBuilder()
            ->select([
                'c.id',
                'c.name',
                "(SELECT COUNT(p.id) AS total FROM $productTable p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=:false AND p.is_active=:true) AS total_active",
                "(SELECT COUNT(p.id) AS total FROM $productTable p JOIN product_channel pc ON p.id=pc.product_id WHERE pc.channel_id=c.id AND p.deleted=:false AND p.is_active=:false) AS total_inactive"
            ])
            ->from($this->connection->quoteIdentifier('channel'), 'c')
            ->where('c.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->fetchAllAssociative();

        $list = [];
        foreach ($data as $row) {
            $list[] = [
                'id'        => $row['id'],
                'name'      => $row['name'],
                'products'  => (int)$row['total_active'] + (int)$row['total_inactive'],
                'active'    => (int)$row['total_active'],
                'notActive' => (int)$row['total_inactive']
            ];
        }

        return ['total' => count($list), 'list' => $list];
    }
}
