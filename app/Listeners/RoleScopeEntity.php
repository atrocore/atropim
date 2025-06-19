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
use Espo\ORM\Entity;

class RoleScopeEntity extends AbstractEntityListener
{
    public function beforeSave(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if (empty($this->getMetadata()->get("scopes.{$entity->get('name')}.hasAttribute"))) {
            $entity->set('createAttributeValueAction', null);
            $entity->set('deleteAttributeValueAction', null);
        }
    }
}
