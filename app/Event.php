<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim;

use Atro\Repositories\LayoutProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Atro\Core\ModuleManager\AfterInstallAfterDelete;
use Pim\Migrations\V1Dot13Dot66;
use Pim\Migrations\V1Dot14Dot6;
use Pim\Migrations\V1Dot14Dot9;

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
            'Brand',
            'Category',
            'Catalog',
            'Channel',
            'Product'
        ];

    /**
     * @var array
     */
    protected $menuItems
        = [
            'Association',
            'Brand',
            'Category',
            'Catalog',
            'Channel'
        ];

    /**
     * @inheritdoc
     */
    public function afterInstall(): void
    {
        // add global search
        $this->addGlobalSearchEntities();

        // add menu items
        $this->addNavigationItems($this->menuItems);

        V1Dot14Dot9::createExamplePreviews($this->getContainer()->get('connection'));
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        $this->removeNavigationItems($this->menuItems);
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
}
