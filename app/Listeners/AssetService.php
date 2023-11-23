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

class AssetService extends AbstractListener
{
    public function beforeUpdateEntity(Event $event): void
    {
        $data = $event->getArgument('data');
        if (property_exists($data, '_id') && property_exists($data, '_sortedIds') && property_exists($data, '_scope') && !empty($data->_sortedIds)) {
            if ($data->_scope === 'Product') {
                $this->getEntityManager()->getRepository('ProductAsset')->updateSortOrder($data->_id, $data->_sortedIds);
                $event->setArgument('result', $this->getService('Asset')->getEntity($data->_itemId));
            } elseif ($data->_scope === 'Category') {
                $this->getEntityManager()->getRepository('CategoryAsset')->updateSortOrder($data->_id, $data->_sortedIds);
                $event->setArgument('result', $this->getService('Asset')->getEntity($data->_itemId));
            }
        }
    }
}
