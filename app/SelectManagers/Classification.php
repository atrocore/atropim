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
    public array $channelIds;

    public function boolFilterAvailableForProduct(&$result)
    {
        $id = $this->getBoolFilterParameter('availableForProduct');

        $product = $this->getEntityManager()->getEntity('Product', $id);
        if (empty($product)) {
            throw new NotFound();
        }

        $channels = $product->get('channels');
        if (!empty($channels)) {
            $this->channelIds = [];
            foreach ($channels as $channel) {
                $this->channelIds[] = $channel->get('id');
            }
            $result['callbacks'][] = [$this, 'applyOnlyAvailableForProductFilter'];
        }
    }

    public function applyOnlyAvailableForProductFilter(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper)
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->leftJoin($tableAlias, 'channel_classification', 'cc', "$tableAlias.id = cc.classification_id and cc.deleted = :false");
        $qb->andwhere("cc.channel_id IS NULL OR cc.channel_id IN (:channelIds)");
        $qb->setParameter('channelIds', $this->channelIds, Mapper::getParameterType($this->channelIds));
        $qb->distinct();
    }
}
