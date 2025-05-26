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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

class AttributePanel extends AbstractSelectManager
{
    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getSelectCondition('onlyForEntity');
        if (!empty($entityName)) {
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }
}
