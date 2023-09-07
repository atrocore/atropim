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

        $sql = "SELECT
                    product_status AS status,
                    COUNT(id)      AS amount
                FROM product
                WHERE deleted=0
                GROUP BY product_status;";

        $sth = $this->getPDO()->prepare($sql);
        $sth->execute();
        $products = $sth->fetchAll(\PDO::FETCH_ASSOC);

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
