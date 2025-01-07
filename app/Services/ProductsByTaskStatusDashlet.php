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

use Espo\Core\Utils\Metadata;

/**
 * Class ProductsByTaskStatusDashlet
 */
class ProductsByTaskStatusDashlet extends AbstractDashletService
{
    /**
     * Int Class
     */
    public function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    /**
     * Get Products by Task status
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];

        $taskStatusDefs = $this->getMetadata()->get(['entityDefs', 'Product', 'fields', 'taskStatus'], []);

        $taskStatusesIds = $taskStatusDefs['optionsIds'] ?? [];
        $taskStatusesNames = $taskStatusDefs['options'] ?? [];

        $result['total'] = count($taskStatusesIds);

        foreach ($taskStatusesIds as $key => $status) {
            $result['list'][] = [
                'id'     => $status,
                'name'   => $taskStatusesNames[$key],
                'amount' => $this->getRepository('Product')->where(['taskStatus*' => "%\"$status\"%"])->count()
            ];
        }

        return $result;
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}
