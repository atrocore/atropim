<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
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
