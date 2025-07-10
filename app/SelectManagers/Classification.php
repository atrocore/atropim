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

use Atro\Core\Exceptions\NotFound;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class Classification extends AbstractSelectManager
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

    protected function boolFilterOnlyForChannel(array &$result): void
    {
        $channelId = (string)$this->getSelectCondition('onlyForChannel');
        if (!empty($channelId)) {
            $result['whereClause'][] = [
                'channelId' => $channelId
            ];
        }
    }
}