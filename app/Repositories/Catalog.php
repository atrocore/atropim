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

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Catalog repository
 */
class Catalog extends Base
{
    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        /** @var string $id */
        $id = $entity->get('id');

        // remove catalog products
        $this->getEntityManager()->nativeQuery("UPDATE product SET deleted=1 WHERE catalog_id='$id'");
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('assignedUserId') || $entity->isAttributeChanged('ownerUserId')) {
            $assignedUserOwnership = $this->getConfig()->get('assignedUserProductOwnership', '');
            $ownerUserOwnership = $this->getConfig()->get('ownerUserProductOwnership', '');

            if ($assignedUserOwnership == 'fromCatalog' || $ownerUserOwnership == 'fromCatalog') {
                foreach ($entity->get('products') as $product) {
                    $toSave = false;

                    if ($assignedUserOwnership == 'fromCatalog'
                        && ($product->get('assignedUserId') == null || $product->get('assignedUserId') == $entity->getFetched('assignedUserId'))) {
                        $product->set('assignedUserId', $entity->get('assignedUserId'));
                        $product->set('assignedUserName', $entity->get('assignedUserName'));
                        $toSave = true;
                    }

                    if ($ownerUserOwnership == 'fromCatalog'
                        && ($product->get('ownerUserId') == null || $product->get('ownerUserId') == $entity->getFetched('ownerUserId'))) {
                        $product->set('ownerUserId', $entity->get('ownerUserId'));
                        $product->set('ownerUserName', $entity->get('ownerUserName'));
                        $toSave = true;
                    }

                    if ($toSave) {
                        $this->getEntityManager()->saveEntity($product, ['skipAll' => true]);
                    }
                }
            }
        }
    }
}
