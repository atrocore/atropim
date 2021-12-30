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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class SettingsController
 */
class SettingsController extends AbstractListener
{
    protected array $removeFields
        = [
            'assignedUserAttributeOwnership' => 'overrideAttributeAssignedUser',
            'ownerUserAttributeOwnership'    => 'overrideAttributeOwnerUser',
            'teamsAttributeOwnership'        => 'overrideAttributeTeams',
            'assignedUserProductOwnership'   => 'overrideProductAssignedUser',
            'ownerUserProductOwnership'      => 'overrideProductOwnerUser',
            'teamsProductOwnership'          => 'overrideProductTeams'
        ];

    /**
     * @param Event $event
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    public function beforeActionUpdate(Event $event): void
    {
        $data = $event->getArgument('data');

        if (property_exists($data, 'productCanLinkedWithNonLeafCategories') && empty($data->productCanLinkedWithNonLeafCategories)) {
            $this->getEntityManager()->getRepository('Product')->unlinkProductsFromNonLeafCategories();
        }

        $channelsLocales = $this
            ->getEntityManager()
            ->getRepository('Channel')
            ->getUsedLocales();

        if (property_exists($data, 'isMultilangActive') && empty($data->isMultilangActive) && count($channelsLocales) > 1) {
            throw new BadRequest($this->getLanguage()->translate('languageUsedInChannel', 'exceptions', 'Settings'));
        }

        if (!empty($data->inputLanguageList)) {
            foreach ($channelsLocales as $locale) {
                if ($locale !== 'main' && !in_array($locale, $event->getArgument('data')->inputLanguageList)) {
                    throw new BadRequest($this->getLanguage()->translate('languageUsedInChannel', 'exceptions', 'Settings'));
                }
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterActionUpdate(Event $event): void
    {
        $data = Json::decode(Json::encode($event->getArgument('data')), true);

        if (isset($data['inputLanguageList']) || isset($data['isMultilangActive'])) {
            $this
                ->getEntityManager()
                ->getRepository('Product')
                ->updateProductsAttributes("SELECT product_id FROM `product_attribute_value` WHERE deleted=0 AND attribute_id IN (SELECT id FROM `attribute` WHERE is_multilang=1 AND deleted=0)", true);
        }

        $qm = false;
        foreach (array_keys($this->removeFields) as $key) {
            if (isset($data[$key]) && $data[$key] != 'notInherit') {
                $qm = true;
                break;
            }
        }

        if ($qm) {
            $this
                ->getContainer()
                ->get('queueManager')
                ->push($this->getLanguage()->translate('updatingOwnershipInformation', 'queueManager', 'Settings'), 'QueueManagerOwnership', $data, 1);
        }

        $this->removeConfigFields();
    }

    /**
     * Remove unnecessary config fields
     */
    protected function removeConfigFields()
    {
        $config = $this->getConfig();

        foreach (array_values($this->removeFields) as $field) {
            if ($config->has($field)) {
                $config->remove($field);
            }
        }
        $config->save();
    }

    protected function isExistsProductsLinkedWithNonLeafCategories(): bool
    {
        return $this->getEntityManager()->getRepository('Product')->isExistsProductsLinkedWithNonLeafCategories();
    }
}