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
use Atro\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // set type Hierarchy to Product entity
        $data['scopes']['Product']['type'] = 'Hierarchy';

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') == 'cascade') {
            $data['clientDefs']['Category']['deleteConfirmation'] = 'Category.messages.categoryRemoveConfirm';
        }

        $data['entityDefs']['Listing']['fields']['classifications']['layoutListDisabled'] = true;
        $data['entityDefs']['Listing']['fields']['classifications']['layoutDetailDisabled'] = true;
        $data['entityDefs']['Listing']['links']['classifications']['layoutRelationshipsDisabled'] = true;

        foreach ($data['scopes'] as $entityType => $scopeDef) {
            if (!empty($scopeDef['primaryEntityId']) && $scopeDef['primaryEntityId'] == 'Product') {
                $data['clientDefs'][$entityType]['relationshipPanels']['files']['dragDrop']['sortField'] = Util::toUnderScore(lcfirst($entityType)) . '_file_mm.sorting';
            }
        }

        $event->setArgument('data', $data);
    }
}
