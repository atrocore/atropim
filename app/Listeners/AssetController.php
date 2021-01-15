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

namespace Pim\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;
use Slim\Http\Request;

/**
 * Class AssetController
 */
class AssetController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionEntityAssets(Event $event)
    {
        /** @var Request $request */
        $request = $event->getArgument('request');

        /** @var array $result */
        $result = $event->getArgument('result');

        if ($request->get('entity') === 'Product') {
            $result = $this->hideProductChannelSpecificAssets((string)$request->get('id'), (array)$result);
            $event->setArgument('result', $result);
        }
    }

    /**
     * @param string $productId
     * @param array  $result
     *
     * @return array
     */
    protected function hideProductChannelSpecificAssets(string $productId, array $result): array
    {
        $product = $this->getEntityManager()->getEntity('Product', $productId);
        if (!empty($product)) {
            $channelsIds = array_column($product->get('channels')->toArray(), 'id');
            foreach ($result['list'] as $key => $type) {
                foreach ($type['assets'] as $k => $asset) {
                    if ($asset['scope'] !== 'Global' && !in_array($asset['channelId'], $channelsIds)) {
                        unset($result['list'][$key]['assets'][$k]);
                    }
                }
                $result['list'][$key]['assets'] = array_values($result['list'][$key]['assets']);
            }
        }

        return $result;
    }
}
