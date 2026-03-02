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

/**
 * Class ProductsByStatusDashlet
 */
class ProductsByStatusDashlet extends AbstractDashletService
{
    /**
     * Get Product by status
     *
     * @return array
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];

        $connection = $this->getEntityManager()->getConnection();

        $products = $connection->createQueryBuilder()
            ->select('p.status AS status, COUNT(p.id) AS amount')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.status')
            ->fetchAllAssociative();


        foreach ($products as $product) {
            $result['list'][] = [
                'id'     => $product['status'],
                'name'   => $product['status'],
                'amount' => (int)$product['amount']
            ];
        }

        $result['total'] = count($result['list']);

        return $result;
    }
}
