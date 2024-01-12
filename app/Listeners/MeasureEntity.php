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
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Exception;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;

class MeasureEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $attributes = $this->getEntityManager()->getRepository('Attribute')
            ->select(['name'])->where(['measureId' => $entity->get('id')])->find()->toArray();

        if (count($attributes) > 0) {
            throw new BadRequest(sprintf($this->translate('measureIsUsedOnAttributes', 'exceptions', 'Measure'),
                join(" , ", array_column($attributes, 'name'))));
        }

    }
}
