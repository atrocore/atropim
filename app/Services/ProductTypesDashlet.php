<?php
declare(strict_types=1);

namespace Pim\Services;

/**
 * Class ProductTypesDashlet
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
