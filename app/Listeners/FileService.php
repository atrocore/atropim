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

class FileService extends AbstractListener
{
    public function beforePutAclMetaForLink(Event $event): void
    {
        $entity = $event->getArgument('entity');
        $link = $event->getArgument('link');

        if ($link !== 'files' || !in_array($event->getArgument('entityFrom')->getEntityName(), ['Product', 'Category'])) {
            return;
        }

        $relationEntity = $entity->relationEntity ?? null;

        $entity->setAvailableAction('setAsMainImage',
            !empty($relationEntity)
            && empty($relationEntity->get('isMainImage'))
            && !empty($entity->get('mimeType'))
            && $entity->isImage()
            && ($this->getUser()->isAdmin() || $this->getAcl()->check($relationEntity, 'edit'))
        );
    }
}
