<?php

declare(strict_types=1);

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class ProductCategory
 *
 * @author r.ratsun@gmail.com
 */
class ProductCategory extends AbstractSelectManager
{
    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        // prepare product types
        $types = implode("','", array_keys($this->getMetadata()->get('pim.productType', [])));

        // prepare custom where
        if (!isset($result['customWhere'])) {
            $result['customWhere'] = '';
        }

        // add filtering by product types
        $result['customWhere'] .= " AND product_category.product_id IN (SELECT id FROM product WHERE type IN ('$types') AND deleted=0)";
    }
}
