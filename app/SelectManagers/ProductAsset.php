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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

class ProductAsset extends AbstractSelectManager
{
    protected array $filterScopes = [];

    public static function createScopePrismBoolFilterName(string $id): string
    {
        return ProductAttributeValue::createScopePrismBoolFilterName($id);
    }

    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        $this->applyScopeBoolFilters($params, $selectParams);

        return $selectParams;
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

    public function applyScopeBoolFilters($params, &$selectParams)
    {
        if (empty($this->filterScopes)) {
            return;
        }

        $channelsIds = [];
        foreach ($this->filterScopes as $channelId) {
            if ($channelId !== 'global') {
                $channelsIds[] = $channelId;
            }
        }
        $channelsIds[] = '';

        $subQuery = "SELECT id FROM product_asset WHERE channel_id IN ('" . implode("','", $channelsIds) . "') AND deleted=0";

        $selectParams['customWhere'] .= " AND product_asset.id IN ($subQuery)";
    }
}
