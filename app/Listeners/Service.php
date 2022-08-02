<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Listeners\AbstractListener;
use Espo\Core\ORM\Entity;

class Service extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @return void
     */
    public function afterCreateEntity(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->hasAttribute('code') && $entity->hasAttribute('name') && empty($entity->get('code'))) {
            $this->setupCode($entity);

            $event->setArgument('entity', $entity);
        }
    }

    /**
     * @param Event $event
     *
     * @return void
     */
    public function afterUpdateEntity(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->hasAttribute('code') && $entity->hasAttribute('name') && $entity->isAttributeChanged('name')) {
            $this->setupCode($entity);

            $event->setArgument('entity', $entity);
        }
    }

    /**
     * @param Entity $entity
     * @param int $index
     *
     * @return void
     */
    protected function setupCode(Entity $entity, int $index = 0): void
    {
        $code = $this->generateCode($entity, $index);

        $exists = $this
            ->getEntityManager()
            ->getRepository($entity->getEntityName())
            ->where(['code' => $code, 'id!=' => $entity->id])
            ->count();

        if ($exists > 0) {
            $this->setupCode($entity, ++$index);
        } else {
            $entity->set('code', $code);
            $this->getEntityManager()->saveEntity($entity, ['skipAll' => true]);
        }
    }

    /**
     * @param Entity $entity
     *
     * @return void
     */
    protected function generateCode(Entity $entity, int $index): string
    {
        $code = strtolower($entity->get('name'));
        $code = preg_replace('/ /', '_', $code);
        $code = preg_replace('/[^a-z_0-9]/', '', $code);

        if (!empty($index)) {
            $code .= '_' . $index;
        }

        return $code;
    }
}
