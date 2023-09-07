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

        $taskStatuses = $this->getInjection('metadata')->get('entityDefs.Product.fields.taskStatus.options');
        $taskStatuses = is_array($taskStatuses) ? $taskStatuses : [];

        $result['total'] = count($taskStatuses);

        foreach ($taskStatuses as $status) {
            $result['list'][] = [
                'id'     => $status,
                'name'   => $status,
                'amount' => $this->getRepository('Product')->where(['taskStatus*' => "%\"$status\"%"])->count()
            ];
        }

        return $result;
    }
}
