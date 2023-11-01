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

namespace Pim;

use Espo\Core\Utils\Config;
use Atro\Core\ModuleManager\AfterInstallAfterDelete;

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
            'Classification'
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
            'Classification'
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
        /** @var Config $config */
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
        /** @var Config $config */
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

        // save
        $config->save();
    }
}
