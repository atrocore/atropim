<?php
declare(strict_types=1);

namespace Pim\Services;

/**
 * Class ProductsByStatusDashlet
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductsByStatusDashlet extends AbstractProductDashletService
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
                WHERE deleted = 0 AND type IN " . $this->getProductTypesCondition() . "
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
