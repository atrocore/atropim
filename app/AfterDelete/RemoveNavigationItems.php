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

namespace Pim\AfterDelete;

use Atro\Core\ModuleManager\AbstractAfterDelete;
use Atro\Core\ModuleManager\Navigation;

/**
 * Removes the entities of the module from the navigation.
 */
class RemoveNavigationItems extends AbstractAfterDelete
{
    private const MENU_ITEMS
        = [
            'Association',
            'Category',
            'Channel'
        ];

    public static function getSortOrder(): int
    {
        return 10;
    }

    public function run(): void
    {
        (new Navigation($this->connection))->removeItems(self::MENU_ITEMS);
    }
}
