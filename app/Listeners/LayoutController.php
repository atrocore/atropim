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

namespace Pim\Listeners;

use Espo\Core\Utils\Json;
use Treo\Core\EventManager\Event;
use Espo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class LayoutController
 */
class LayoutController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionRead(Event $event)
    {
        /** @var string $scope */
        $scope = $event->getArgument('params')['scope'];

        /** @var string $name */
        $name = $event->getArgument('params')['name'];

        /** @var bool $isAdminPage */
        $isAdminPage = $event->getArgument('request')->get('isAdminPage') === 'true';

        $method = 'modify' . $scope . ucfirst($name);
        $methodAdmin = $method . 'Admin';

        if (!$isAdminPage && method_exists($this, $method)) {
            $this->{$method}($event);
        } else if ($isAdminPage && method_exists($this, $methodAdmin)) {
            $this->{$methodAdmin}($event);
        }
    }

    /**
     * @param Event $event
     */
    protected function modifyAssetDetailSmall(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);
        $result[0]['rows'][] = [['name' => 'scope'], ['name' => 'channel']];
        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyAssetListSmallForProduct(Event $event): void
    {
        $data = Json::decode($this->getContainer()->get('layout')->get('Asset', 'listSmall'), true);
        $data[] = ['name' => 'channel'];

        $event->setArgument('result', Json::encode($data));
    }

    /**
     * @param Event $event
     */
    protected function modifyAttributeDetail(Event $event)
    {
        /** @var array $result */
        $result = Json::decode($event->getArgument('result'), true);

        $names = [['name' => 'name']];

        if ($this->getConfig()->get('isMultilangActive', false)) {
            $multilangField = ['name' => 'isMultilang', 'inlineEditDisabled' => true];
            $rowsCount = count($result[0]['rows']);

            if (count($result[0]['rows'][$rowsCount - 1]) == 2 && $result[0]['rows'][$rowsCount - 1][1] == false) {
                $result[0]['rows'][$rowsCount - 1][1] = $multilangField;
            } else {
                $result[0]['rows'][] = [$multilangField, false];
            }

            foreach ($this->getInputLanguageList() as $locale => $key) {
                $names[] = ['name' => 'name' . $key];
            }

            if (count($names) % 2 != 0) {
                $names[] = false;
            }
        } else {
            $names[] = false;
        }

        $result[0]['rows'][] = [['name' => 'typeValue'], false];

        $result[0]['rows'] = array_merge($result[0]['rows'], array_chunk($names, 2));

        $event->setArgument('result', Json::encode($result));
    }

    /**
     * @param Event $event
     */
    protected function modifyProductDetailSmall(Event $event)
    {
        /** @var array $result */
        $result = Json::decode($event->getArgument('result'), true);

        $result[0]['rows'][] = [['name' => 'isActiveForChannel'], false];

        $event->setArgument('result', Json::encode($result));
    }

    /**
     * @param Event $event
     */
    protected function modifyChannelDetailSmall(Event $event)
    {
        $this->modifyProductDetailSmall($event);
    }

    /**
     * @param Event $event
     */
    protected function modifyAttributeDetailSmall(Event $event)
    {
        $this->modifyAttributeDetail($event);
    }

    /**
     * @param Event $event
     */
    protected function modifyProductRelationshipsAdmin(Event $event)
    {
    }

    /**
     * @param Event $event
     */
    protected function modifyCategoryRelationshipsAdmin(Event $event)
    {
    }

    /**
     * @return array
     */
    protected function getInputLanguageList(): array
    {
        $result = [];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $result[$locale] = ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        return $result;
    }
}
