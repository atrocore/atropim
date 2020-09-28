<?php

declare(strict_types=1);

namespace Pim\Services;

/**
 * Class QueueManagerDuplicateProduct
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class QueueManagerDuplicateProduct extends \Treo\Services\QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        if (empty($data['productId']) || empty($data['catalogId'])) {
            return false;
        }

        // get service
        $service = $this->getContainer()->get('serviceFactory')->create('Product');

        // prepare product data
        $productData = $service->getDuplicateAttributes($data['productId']);
        $productData->catalogId = $data['catalogId'];

        // create entity
        $service->createEntity($productData);

        return true;
    }
}
