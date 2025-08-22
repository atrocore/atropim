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

namespace Pim\SelectManagers;

use Atro\Core\Exceptions\Error;
use Pim\Core\SelectManagers\AbstractSelectManager;

class Listing extends AbstractSelectManager
{
    /**
     * @param $result
     *
     * @throws Error
     */
    protected function boolFilterForComponentChannels(&$result): void
    {
        $componentId = (string)$this->getSelectCondition('forComponentChannels');

        if (!empty($componentId)) {
            $component = $this->getEntityManager()->getEntity('Component', $componentId);
            if (!empty($component)) {
                $result['whereClause'][] = [
                    'channelId' => array_column($component->get('channels')->toArray(), 'id')
                ];
            }
        }
    }
}
