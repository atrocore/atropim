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
use Treo\Core\EventManager\Event;

/**
 * Class AssociationEntity
 */
class AssociationEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if (!$this->isCodeValid($entity)) {
            throw new BadRequest($this->translate('codeIsInvalid', 'exceptions', 'Global'));
        }

        if (empty($entity->get('isActive')) && $this->hasProduct($entity, true)) {
            throw new BadRequest($this->translate('youCanNotDeactivateAssociationWithActiveProducts', 'exceptions', 'Association'));
        }

        if ($entity->isAttributeChanged('backwardAssociationId')) {
            $this->updateBackwardAssociation($entity);
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if ($this->hasProduct($entity)) {
            throw new BadRequest($this->translate('associationIsLinkedWithProducts', 'exceptions', 'Association'));
        }
    }

    /**
     * @param Entity $entity
     */
    protected function updateBackwardAssociation(Entity $entity)
    {
        if (!empty($entity->skipUpdateBackwardAssociation)) {
            return;
        }

        // unlink old
        if (!empty($entity->getFetched('backwardAssociationId'))) {
            $association = $this->getEntityManager()->getEntity('Association', $entity->getFetched('backwardAssociationId'));
            $association->set('backwardAssociationId', null);
            $association->skipUpdateBackwardAssociation = 1;
            $this->getEntityManager()->saveEntity($association);
        }

        // link
        if (!empty($entity->get('backwardAssociationId'))) {
            $association = $this->getEntityManager()->getEntity('Association', $entity->get('backwardAssociationId'));
            $association->set('backwardAssociationId', $entity->get('id'));
            $association->skipUpdateBackwardAssociation = 1;
            $this->getEntityManager()->saveEntity($association);
        }
    }

    /**
     * Is association used in product(s)
     *
     * @param Entity $entity
     * @param bool   $isActive
     *
     * @return bool
     */
    protected function hasProduct(Entity $entity, bool $isActive = false): bool
    {
        // prepare attribute id
        $associationId = $entity->get('id');

        $sql
            = "SELECT
                  COUNT(ap.id) as total
                FROM associated_product AS ap
                  JOIN product AS pm 
                    ON pm.id = ap.main_product_id AND pm.deleted = 0
                  JOIN product AS pr 
                    ON pr.id = ap.related_product_id AND pr.deleted = 0
                WHERE ap.deleted = 0 AND ap.association_id = '{$associationId}'";

        if ($isActive) {
            $sql .= " AND (pm.is_active=1 OR pr.is_active=1)";
        }

        // execute
        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        // get data
        $data = $sth->fetch(\PDO::FETCH_ASSOC);

        return !empty($data['total']);
    }
}
