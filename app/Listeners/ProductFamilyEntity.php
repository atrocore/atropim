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
use Pim\Entities\ProductFamilyAttribute;
use Treo\Core\EventManager\Event;

/**
 * Class ProductFamilyEntity
 */
class ProductFamilyEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if (!$this->isCodeValid($entity)) {
            throw new BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
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

        $this->validRelationsWithProduct($entity->id);
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event): void
    {
        $this->removeProductFamilyAttribute($event);
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'productFamilyAttributes'
            && !empty($foreign = $event->getArgument('foreign'))
            && !is_string($foreign)) {
            $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->removeCollectionByProductFamilyAttribute($foreign->get('id'));
        }
    }

    /**
     * Validation ProductFamily relations Product
     *
     * @param string $id
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProduct(string $id): void
    {
        if ($this->hasProducts($id)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Product Family is used in products',
                    'exceptions',
                    'ProductFamily'
                )
            );
        }
    }

    /**
     * Has Products relations ProductFamily
     *
     * @param string $id
     *
     * @return bool
     */
    protected function hasProducts(string $id): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['productFamilyId' => $id])
            ->count();

        return !empty($count);
    }

    /**
     * @param Event $event
     */
    protected function removeProductFamilyAttribute(Event $event): void
    {
        /** @var ProductFamilyAttribute[] $productFamilyAttributes */
        $productFamilyAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['id'])
            ->where(['productFamilyId' => $event->getArgument('entity')->get('id')])
            ->find();
        
        foreach ($productFamilyAttributes as $productFamilyAttribute) {
            $this->getEntityManager()->removeEntity($productFamilyAttribute);
        }
    }
}
