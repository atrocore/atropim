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
 * Counts the products of each status.
 */
class ProductsByStatus implements DashletInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getData(array $params): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('status, COUNT(id) AS amount')
            ->from($this->connection->quoteIdentifier('product'))
            ->where('deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('status')
            ->fetchAllAssociative();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id'     => $row['status'],
                'name'   => $row['status'],
                'amount' => (int)$row['amount']
            ];
        }

        return ['total' => count($list), 'list' => $list];
    }
}
