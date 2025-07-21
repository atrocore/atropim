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

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') == 'cascade') {
            $data['clientDefs']['Catalog']['relationshipPanels']['categories']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
            $data['clientDefs']['Category']['relationshipPanels']['catalogs']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
        }

        $data['entityDefs']['Listing']['fields']['classifications']['layoutListDisabled'] = true;
        $data['entityDefs']['Listing']['fields']['classifications']['layoutDetailDisabled'] = true;
        $data['entityDefs']['Listing']['links']['classifications']['layoutRelationshipsDisabled'] = true;

        $event->setArgument('data', $data);
    }
}
