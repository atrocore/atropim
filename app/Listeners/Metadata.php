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

/**
 * Class Metadata
 *
 * @package Pim\Listeners
 */
class Metadata extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        $data = $this->productOwnership($data);

        $data = $this->attributeOwnership($data);

        $event->setArgument('data', $data);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function productOwnership(array $data): array
    {
        if ($data['scopes']['Product']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']["assignedUserProductOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "sameAsCreator",
                    "notInherit"
                ],
                "default"   =>  "sameAsCreator"
            ];
        }

        if ($data['scopes']['Product']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']["ownerUserProductOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "sameAsCreator",
                    "notInherit"
                ],
                "default"   =>  "sameAsCreator"
            ];
        }

        if ($data['scopes']['Product']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']["teamsProductOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "notInherit"
                ],
                "default"   =>  "notInherit"
            ];
        }

        $data = $this->setProductOwnershipSettings($data);

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function attributeOwnership(array $data): array
    {
        if ($data['scopes']['ProductAttributeValue']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']["assignedUserAttributeOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "sameAsCreator",
                    "notInherit"
                ],
                "default"   =>  "sameAsCreator"
            ];
        }

        if ($data['scopes']['ProductAttributeValue']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']["ownerUserAttributeOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "sameAsCreator",
                    "notInherit"
                ],
                "default"   =>  "sameAsCreator"
            ];
        }

        if ($data['scopes']['ProductAttributeValue']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']["teamsAttributeOwnership"] = [
                "type"      => "enum",
                "options"   => [
                    "notInherit"
                ],
                "default"   =>  "notInherit"
            ];
        }

        $data = $this->setProductAttributeValueSettings($data);

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function setProductOwnershipSettings(array $data): array
    {
        if ($data['scopes']['Catalog']['hasAssignedUser'] ?? false) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if ($data['scopes']['Catalog']['hasOwner'] ?? false) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if ($data['scopes']['Catalog']['hasTeam'] ?? false) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromCatalog';
        }

        if ($data['scopes']['ProductFamily']['hasAssignedUser'] ?? false) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if ($data['scopes']['ProductFamily']['hasOwner'] ?? false) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if ($data['scopes']['ProductFamily']['hasTeam'] ?? false) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromProductFamily';
        }

        return $data;
    }

    protected function setProductAttributeValueSettings(array $data): array
    {
        if ($data['scopes']['Attribute']['hasAssignedUser'] ?? false) {
            $data['entityDefs']['Settings']['fields']['assignedUserAttributeOwnership']['options'][] = 'fromAttribute';
        }

        if ($data['scopes']['Attribute']['hasOwner'] ?? false) {
            $data['entityDefs']['Settings']['fields']['ownerUserAttributeOwnership']['options'][] = 'fromAttribute';
        }

        if ($data['scopes']['Attribute']['hasTeam'] ?? false) {
            $data['entityDefs']['Settings']['fields']['teamsAttributeOwnership']['options'][] = 'fromAttribute';
        }

        if ($data['scopes']['Product']['hasAssignedUser'] ?? false) {
            $data['entityDefs']['Settings']['fields']['assignedUserAttributeOwnership']['options'][] = 'fromProduct';
        }

        if ($data['scopes']['Product']['hasOwner'] ?? false) {
            $data['entityDefs']['Settings']['fields']['ownerUserAttributeOwnership']['options'][] = 'fromProduct';
        }

        if ($data['scopes']['Product']['hasTeam'] ?? false) {
            $data['entityDefs']['Settings']['fields']['teamsAttributeOwnership']['options'][] = 'fromProduct';
        }

        return $data;
    }
}