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

class FileEntity extends AbstractEntityListener
{
    public function afterRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')
            ->select(['id'])
            ->where(['attributeType' => 'file', 'referenceValue' => $entity->get('id')])
            ->find();
        
        foreach ($pavs as $pav) {
            $this->getEntityManager()->removeEntity($pav);
        }
    }
}
