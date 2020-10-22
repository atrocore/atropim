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
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Entities\Association;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class AssociatedProductEntity
 */
class AssociatedProductEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->get('mainProductId') == $entity->get('relatedProductId')) {
            throw new BadRequest($this->exception('itselfAssociation'));
        }

        if ($entity->isNew()) {
            if (!$this->isUnique($entity)) {
                throw new BadRequest($this->exception('productAssociationAlreadyExists'));
            }
        }
    }

    /**
     * @param Event $event
     */
    public function beforeRemove(Event $event)
    {
        /** @var Entity $associatedProduct */
        $associatedProduct = $event->getArgument('entity');

        if (empty($associatedProduct->get('bothDirections')) || !empty($associatedProduct->skipBackwardDelete)) {
            return;
        }

        /** @var string $backwardAssociationId */
        $backwardAssociationId = $associatedProduct->get('backwardAssociationId');

        if (!empty($backwardAssociationId)) {
            $backwards = $associatedProduct->get('backwardAssociation')->get('associatedProducts');
            if ($backwards->count() > 0) {
                foreach ($backwards as $backward) {
                    if ($backward->get('mainProductId') == $associatedProduct->get('relatedProductId')
                        && $backward->get('relatedProductId') == $associatedProduct->get('mainProductId')
                        && $backward->get('associationId') == $backwardAssociationId) {
                        $backward->skipBackwardDelete = true;
                        $this->getEntityManager()->removeEntity($backward);
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        $exist = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->select(['id'])
            ->where(
                [
                    'associationId'    => $entity->get('associationId'),
                    'mainProductId'    => $entity->get('mainProductId'),
                    'relatedProductId' => $entity->get('relatedProductId')
                ]
            )
            ->findOne();

        return empty($exist);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getLanguage()->translate($key, 'exceptions', 'Product');
    }
}
