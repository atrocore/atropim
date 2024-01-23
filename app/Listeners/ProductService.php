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


namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ProductService extends AbstractListener
{
    public function inheritAllForChild(Event $event): void
    {
        $parent = $event->getArgument('parent');
        $child = $event->getArgument('child');

        $this->getService('Product')->inheritedAllFromParent($parent, $child);
    }
}
