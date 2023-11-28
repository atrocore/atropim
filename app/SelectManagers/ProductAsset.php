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

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class ProductAsset extends AbstractSelectManager
{
    protected array $filterScopes = [];

    public static function createScopePrismBoolFilterName(string $id): string
    {
        return ProductAttributeValue::createScopePrismBoolFilterName($id);
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'filterByChannel'];
    }

    public function applyBoolFilter($filterName, &$result)
    {
        if (self::createScopePrismBoolFilterName('global') === $filterName) {
            $this->filterScopes[] = 'global';
        }

        foreach ($this->getMetadata()->get(['clientDefs', 'ProductAsset', 'channels'], []) as $channel) {
            if (self::createScopePrismBoolFilterName($channel['id']) === $filterName) {
                $this->filterScopes[] = $channel['id'];
            }
        }

        parent::applyBoolFilter($filterName, $result);
    }

    public function filterByChannel(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper)
    {
        if (empty($this->filterScopes)) {
            return;
        }

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $channelsIds = [];
        foreach ($this->filterScopes as $channelId) {
            if ($channelId !== 'global') {
                $channelsIds[] = $channelId;
            }
        }
        $channelsIds[] = '';

        $qb->andWhere("{$tableAlias}.id IN (SELECT ps.id FROM product_asset ps WHERE ps.channel_id IN (:channelsIds) AND ps.deleted=:false)");
        $qb->setParameter('channelsIds', $channelsIds, Mapper::getParameterType($channelsIds));
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
    }
}
