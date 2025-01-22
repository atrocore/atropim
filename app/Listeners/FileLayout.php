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
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractLayoutListener;

class FileLayout extends AbstractLayoutListener
{
    protected function list(Event $event)
    {
        if (!$this->isCustomLayout($event) && $this->isRelatedLayout($event)) {
            $result = $event->getArgument('result');
            $result[] = ['name' => 'ProductFile__channel'];

            $event->setArgument('result', $result);
        }
    }

    protected function detail(Event $event)
    {
        if (!$this->isCustomLayout($event) && !$this->isRelatedLayout($event)) {
            $result = $event->getArgument('result');

            $result[0]['rows'][] = [['name' => 'ProductFile__isMainImage'], ['name' => 'ProductFile__sorting']];
            $result[0]['rows'][] = [['name' => 'ProductFile__channel'], false];

            $result[0]['rows'][] = [['name' => 'CategoryFile__isMainImage'], ['name' => 'CategoryFile__sorting']];

            $event->setArgument('result', $result);
        }
    }
}
