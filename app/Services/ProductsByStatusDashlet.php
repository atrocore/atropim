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
            ->select('p.product_status AS status, COUNT(p.id) AS amount')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('p.product_status')
            ->fetchAllAssociative();

        $fieldDefs = $this->getInjection('metadata')->get(['entityDefs', 'Product', 'fields', 'productStatus']);

        foreach ($products as $product) {
            $name = $product['status'];
            if (!empty($fieldDefs['optionsIds'])) {
                $key = array_search($product['status'], $fieldDefs['optionsIds']);
                if ($key !== false && isset($fieldDefs['options'][$key])) {
                    $name = $fieldDefs['options'][$key];
                }
            }

            $result['list'][] = [
                'id'     => $product['status'],
                'name'   => $name,
                'amount' => (int)$product['amount']
            ];
        }

        $result['total'] = count($result['list']);

        return $result;
    }
}
