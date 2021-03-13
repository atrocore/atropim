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

namespace Pim;

use Treo\Core\ModuleManager\AfterInstallAfterDelete;

/**
 * Class Event
 */
class Event extends AfterInstallAfterDelete
{
    /**
     * @var array
     */
    protected $searchEntities
        = [
            'Association',
            'Attribute',
            'AttributeGroup',
            'Brand',
            'Category',
            'Catalog',
            'Channel',
            'Product',
            'ProductFamily'
        ];

    /**
     * @var array
     */
    protected $menuItems
        = [
            'Association',
            'Attribute',
            'AttributeGroup',
            'Brand',
            'Category',
            'Catalog',
            'Channel',
            'Product',
            'ProductFamily'
        ];

    /**
     * @inheritdoc
     */
    public function afterInstall(): void
    {
        // add global search
        $this->addGlobalSearchEntities();

        // add menu items
        $this->addMenuItems();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
    }

    /**
     * Add global search entities
     */
    protected function addGlobalSearchEntities(): void
    {
        // get config
        $config = $this->getContainer()->get('config');

        // get config data
        $globalSearchEntityList = $config->get("globalSearchEntityList", []);

        foreach ($this->searchEntities as $entity) {
            if (!in_array($entity, $globalSearchEntityList)) {
                $globalSearchEntityList[] = $entity;
            }
        }

        // set to config
        $config->set('globalSearchEntityList', $globalSearchEntityList);

        // save
        $config->save();
    }

    /**
     * Add menu items
     */
    protected function addMenuItems()
    {
        // get config
        $config = $this->getContainer()->get('config');

        // get config data
        $tabList = $config->get("tabList", []);
        $quickCreateList = $config->get("quickCreateList", []);
        $twoLevelTabList = $config->get("twoLevelTabList", []);

        $twoLevelTabListItems = [];
        foreach ($twoLevelTabList as $item) {
            if (is_string($item)) {
                $twoLevelTabListItems[] = $item;
            } else {
                $twoLevelTabListItems = array_merge($twoLevelTabListItems, $item->items);
            }
        }

        foreach ($this->menuItems as $item) {
            if (!in_array($item, $tabList)) {
                $tabList[] = $item;
            }
            if (!in_array($item, $quickCreateList)) {
                $quickCreateList[] = $item;
            }
            if (!in_array($item, $twoLevelTabListItems)) {
                $twoLevelTabList[] = $item;
            }
        }

        // set to config
        $config->set('tabList', $tabList);
        $config->set('quickCreateList', $quickCreateList);
        $config->set('twoLevelTabList', $twoLevelTabList);

        // set application name
        if (in_array($config->get('applicationName'), ['AtroCORE', 'AtroDAM'])) {
            $config->set('applicationName', 'AtroPIM');
        }

        // save
        $config->save();
    }
}
