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

class ClassificationAttributeLayout extends AbstractLayoutListener
{
    protected function list(Event $event)
    {
        if ($this->isRelatedLayout($event)) {
            $result = $event->getArgument('result');
            foreach ($result as &$item) {
                if (!empty($item['name']) && $item['name'] === 'attribute') {
                    $item['view'] = 'pim:views/classification-attribute/fields/attribute-with-inheritance-sign';
                }
            }
            $event->setArgument('result', $result);
        }
    }


}
