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

namespace Pim\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\Utils\Config;

/**
 * Adds the entities of the module to the global search.
 */
class AddGlobalSearchEntities implements AfterInstallInterface
{
    private const SEARCH_ENTITIES
        = [
            'Association',
            'Brand',
            'Category',
            'Channel',
            'Product'
        ];

    public function __construct(private readonly Config $config)
    {
    }

    public static function getSortOrder(): int
    {
        return 10;
    }

    public function run(): void
    {
        $globalSearchEntityList = $this->config->get('globalSearchEntityList', []);

        foreach (self::SEARCH_ENTITIES as $entity) {
            if (!in_array($entity, $globalSearchEntityList)) {
                $globalSearchEntityList[] = $entity;
            }
        }

        $this->config->set('globalSearchEntityList', $globalSearchEntityList);
        $this->config->save();
    }
}
