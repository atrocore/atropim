<?php

declare(strict_types=1);

namespace Pim;

use Treo\Core\ModuleManager\AbstractEvent;
use DamCommon\Services\MigrationPimImage;

/**
 * Class Event
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Event extends AbstractEvent
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
        //for Pim
        $this->migratePimImageToDam();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // delete global search
        $this->deleteGlobalSearchEntities();

        // delete menu items
        $this->deleteMenuItems();
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
     * Delete global search entities
     */
    protected function deleteGlobalSearchEntities(): void
    {
        // get config
        $config = $this->getContainer()->get('config');

        $globalSearchEntityList = [];
        foreach ($config->get("globalSearchEntityList", []) as $entity) {
            if (!in_array($entity, $this->searchEntities)) {
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

        foreach ($this->menuItems as $item) {
            if (!in_array($item, $tabList)) {
                $tabList[] = $item;
            }
            if (!in_array($item, $quickCreateList)) {
                $quickCreateList[] = $item;
            }
            if (!in_array($item, $twoLevelTabList)) {
                $twoLevelTabList[] = $item;
            }
        }

        // set to config
        $config->set('tabList', $tabList);
        $config->set('quickCreateList', $quickCreateList);
        $config->set('twoLevelTabList', $twoLevelTabList);

        if ($config->get('applicationName') == 'TreoCore') {
            $config->set('applicationName', 'TreoPIM');
        }

        // save
        $config->save();
    }

    /**
     * Delete menu items
     */
    protected function deleteMenuItems()
    {
        // get config
        $config = $this->getContainer()->get('config');

        // for tabList
        $tabList = [];
        foreach ($config->get("tabList", []) as $entity) {
            if (!in_array($entity, $this->menuItems)) {
                $tabList[] = $entity;
            }
        }
        $config->set('tabList', $tabList);

        // for quickCreateList
        $quickCreateList = [];
        foreach ($config->get("quickCreateList", []) as $entity) {
            if (!in_array($entity, $this->menuItems)) {
                $quickCreateList[] = $entity;
            }
        }
        $config->set('quickCreateList', $quickCreateList);

        // for twoLevelTabList
        $twoLevelTabList = [];
        foreach ($config->get("twoLevelTabList", []) as $entity) {
            if (!in_array($entity, $this->menuItems)) {
                $twoLevelTabList[] = $entity;
            }
        }
        $config->set('twoLevelTabList', $twoLevelTabList);

        // save
        $config->save();
    }

    /**
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function migratePimImageToDam(): void
    {
        $config = $this->getContainer()->get('config');

        if (!empty($config->get('isInstalled'))
            && $config->get('pimAndDamInstalled') === false
            && $this->getContainer()->get('metadata')->isModuleInstalled('Dam')) {
            //migration pimImage
            $migrationPimImage = new MigrationPimImage();
            $migrationPimImage->setContainer($this->getContainer());
            $migrationPimImage->run();

            //set flag about installed Pim and Image
            $config->set('pimAndDamInstalled', true);
            $config->save();
        }
    }
}
