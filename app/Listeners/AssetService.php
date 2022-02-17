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

use Dam\Entities\Asset;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Treo\Core\EventManager\Event;
use Espo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

class AssetService extends AbstractListener
{
    public function beforeUpdateEntity(Event $event): void
    {
        $data = $event->getArgument('data');
        if (!property_exists($data, '_relationEntity') || !property_exists($data, '_relationEntityId') || $data->_relationEntity !== 'Product') {
            return;
        }

        $assetData = $this->getEntityManager()->getRepository('Product')->getAssetData($data->_relationEntityId, $event->getArgument('id'));
        if (empty($assetData)) {
            return;
        }

        if ($assetData['channel'] != $data->channel && !empty($assetData['is_main_image'])) {
            throw new BadRequest($this->getLanguage()->translate("scopeForTheImageMarkedAsMainCannotBeChanged", 'exceptions', 'Asset'));
        }
    }
}
