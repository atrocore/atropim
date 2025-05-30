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
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class EntityFieldEntity extends AbstractEntityListener
{
    public function beforeSave(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.hasAttribute")) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')
                ->where([
                    'entityId' => $entity->get('entityId'),
                    'code'     => $entity->get('code')
                ])
                ->findOne();

            if (!empty($attribute)) {
                throw new BadRequest("Attribute with such code exists for the {$entity->get('entityId')}.");
            }
        }
    }
}
