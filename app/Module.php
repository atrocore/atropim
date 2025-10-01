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

use Atro\Core\ModuleManager\AbstractModule;

class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5120;
    }

    public function loadLayouts(string $scope, string $name, array &$data)
    {
        if ($scope === 'Product') {
            // ignore data coming from Atro
            $data = [];
        }

        parent::loadLayouts($scope, $name, $data);
    }
}
