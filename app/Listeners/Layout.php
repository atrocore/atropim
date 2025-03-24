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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Layout extends AbstractListener
{
    public function afterGetLayoutContent(Event $event): void
    {
        $scope = $event->getArgument('params')['scope'] ?? null;
        $viewType = $event->getArgument('params')['viewType'] ?? null;
        $result = $event->getArgument('result') ?? [];
        $resultUpdated = false;

        if ($this->getMetadata()->get("scopes.{$scope}.attributeValueEntity")) {
            if ($viewType === 'list' && empty($result)) {
                $resultUpdated = true;
                $result = [
                    [
                        "name"        => "foo",
                        "notSortable" => true
                    ],
                    [
                        "name"        => "attribute",
                        "notSortable" => true
                    ],
                    [
                        "name"        => "value",
                        "notSortable" => true
                    ]
                ];
            }

            if ($viewType === 'detail' and empty($result[0]['rows'][0][0])) {
                $resultUpdated = true;
                $result = [
                    [
                        "label" => "Details",
                        "style" => "default",
                        "rows"  => [
                            [
                                [
                                    "name" => "attribute"
                                ],
                                [
                                    "name" => "foo"
                                ]
                            ],
                            [
                                [
                                    "name" => "language"
                                ],
                                false
                            ],
                            [
                                [
                                    "name" => "value"
                                ],
                                false
                            ]
                        ]
                    ]
                ];
            }
        }

        if ($resultUpdated) {
            $event->setArgument('result', $result);
        }
    }
}
