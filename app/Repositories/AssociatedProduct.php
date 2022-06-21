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

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class AssociatedProduct extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('mainProductId') == $entity->get('relatedProductId')) {
            throw new BadRequest($this->getInjection('language')->translate('itselfAssociation', 'exceptions', 'Product'));
        }
    }

    public function removeByProductId(string $productId): void
    {
        $productId = $this->getPDO()->quote($productId);
        $this->getPDO()->exec("DELETE FROM `associated_product` WHERE main_product_id=$productId OR related_product_id=$productId");
    }

    public function remove(Entity $entity, array $options = [])
    {
//        /** @var string $backwardAssociationId */
//        $backwardAssociationId = $associatedProduct->get('backwardAssociationId');
//
//        if (!empty($backwardAssociationId)) {
//            $backwards = $associatedProduct->get('backwardAssociation')->get('associatedProducts');
//            if ($backwards->count() > 0) {
//                foreach ($backwards as $backward) {
//                    if ($backward->get('mainProductId') == $associatedProduct->get('relatedProductId')
//                        && $backward->get('relatedProductId') == $associatedProduct->get('mainProductId')
//                        && $backward->get('associationId') == $backwardAssociationId) {
//                        $backward->skipBackwardDelete = true;
//                        $this->getEntityManager()->removeEntity($backward);
//                    }
//                }
//            }
//        }

        $this->beforeRemove($entity, $options);
        $result = $this->deleteFromDb($entity->get('id'));
        if ($result) {
            $this->afterRemove($entity, $options);
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
