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

namespace Pim\Jobs;

use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;

class DuplicateProduct extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['productId']) || empty($data['catalogId'])) {
            return;
        }

        // get service
        $service = $this->getServiceFactory()->create('Product');

        // prepare product data
        $productData = $service->getDuplicateAttributes($data['productId']);
        $productData->catalogId = $data['catalogId'];

        // create entity
        $service->createEntity($productData);
    }
}
