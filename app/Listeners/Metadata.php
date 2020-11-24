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

/**
 * Class Metadata
 *
 * @package Pim\Listeners
 */
class Metadata extends \Treo\Listeners\AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        $data = $this->setProductOwnershipSettings($data);

        $event->setArgument('data', $data);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function setProductOwnershipSettings(array $data): array
    {
        if (isset($data['scopes']['Catalog']['hasAssignedUser']) && $data['scopes']['Catalog']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['Catalog']['hasOwner']) && $data['scopes']['Catalog']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['Catalog']['hasTeam']) && $data['scopes']['Catalog']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['ProductFamily']['hasAssignedUser']) && $data['scopes']['ProductFamily']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if (isset($data['scopes']['ProductFamily']['hasOwner']) && $data['scopes']['ProductFamily']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if (isset($data['scopes']['ProductFamily']['hasTeam']) && $data['scopes']['ProductFamily']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromProductFamily';
        }

        return $data;
    }
}