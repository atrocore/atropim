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
use Atro\Listeners\AbstractLayoutListener;

class RoleScopeLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        /** @var array $result */
        $result = $event->getArgument('result');

        $result[1]['rows'][] = [
            ['name' => 'createAttributeValueAction'],
            ['name' => 'deleteAttributeValueAction']
        ];

        $event->setArgument('result', $result);
    }

    public function relationships(Event $event): void
    {
        /** @var array $result */
        $result = $event->getArgument('result');

        $result[] = ['name' => 'attributePanels'];
        $result[] = ['name' => 'attributes'];

        $event->setArgument('result', $result);
    }
}
