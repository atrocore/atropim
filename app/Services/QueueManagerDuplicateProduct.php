<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Services\QueueManagerBase;

/**
 * Class QueueManagerDuplicateProduct
 */
class QueueManagerDuplicateProduct extends QueueManagerBase
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
