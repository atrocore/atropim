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
    public const DASHLETS_DATA = [
        'layout' => [
            [
                'name' => 'Insights',
                'layout' => [
                    [
                        'id' => 'd80992',
                        'name' => 'ProductStatusOverview',
                        'x' => 0,
                        'y' => 0,
                        'width' => 2,
                        'height' => 2
                    ],
                    [
                        'id' => 'd811129',
                        'name' => 'Stream',
                        'x' => 2,
                        'y' => 0,
                        'width' => 2,
                        'height' => 4
                    ],
                    [
                        'id' => 'd550670',
                        'name' => 'ProductsByTag',
                        'x' => 0,
                        'y' => 2,
                        'width' => 2,
                        'height' => 2
                    ],
                    [
                        'id' => 'd556889',
                        'name' => 'DataSyncErrorsExport',
                        'x' => 0,
                        'y' => 4,
                        'width' => 2,
                        'height' => 2
                    ],
                    [
                        'id' => 'd403401',
                        'name' => 'DataSyncErrorsImport',
                        'x' => 2,
                        'y' => 4,
                        'width' => 2,
                        'height' => 2
                    ]
                ]
            ]
        ],
        'options' => []
    ];

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
