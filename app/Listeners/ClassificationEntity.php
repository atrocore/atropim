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
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Repositories\RDB;

class ClassificationEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event)
    {
        /** @var \Espo\ORM\Entity $entity */
        $entity = $event->getArgument('entity');

        if (empty($entity->get('entityId'))) {
            return;
        }

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($entity->get('entityId') . 'Classification');
        $record = $repository->where(['classificationId' => $entity->get('id')])->findOne();

        if (!empty($record)) {
            throw new BadRequest($this->translate('classificationHasRecords', 'exceptions', 'Classification'));
        }
    }
}
