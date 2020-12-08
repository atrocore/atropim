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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class ProductFamily
 */
class ProductFamily extends Base
{
    /**
     * @inheritDoc
     */
    public function unrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'productFamilyAttributes') {
            // prepare id
            if ($foreign instanceof Entity) {
                $id = $foreign->get('id');
            } elseif (is_string($foreign)) {
                $id = $foreign;
            } else {
                throw new BadRequest("'Remove all relations' action is blocked for such relation");
            }

            // make product attribute as custom
            $sql = "UPDATE product_attribute_value SET product_family_attribute_id=NULL,is_required=1 WHERE product_family_attribute_id='$id';";

            // unlink
            $sql .= "UPDATE product_family_attribute SET deleted=1 WHERE id='$id'";

            // execute
            $this->getEntityManager()->nativeQuery($sql);

            return true;
        }

        return parent::unrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @param string $id
     * @param string $scope
     *
     * @return array
     */
    public function getLinkedAttributesIds(string $id, string $scope = 'Global'): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['attributeId'])
            ->where(['productFamilyId' => $id, 'scope' => $scope])
            ->find()
            ->toArray();

        return array_column($data, 'attributeId');
    }

    /**
     * @param array       $productFamiliesIds
     * @param string|null $attributeGroupId
     *
     * @return array
     */
    public function getLinkedWithAttributeGroup(array $productFamiliesIds, ?string $attributeGroupId): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['id'])
            ->distinct()
            ->join('attribute')
            ->where(
                [
                    'productFamilyId'            => $productFamiliesIds,
                    'attribute.attributeGroupId' => ($attributeGroupId != '') ? $attributeGroupId : null
                ]
            )
            ->find()
            ->toArray();

        return array_column($data, 'id');
    }

    /**
     * @inheritdoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        // unlink products
        if (!empty($products = $entity->get('products'))) {
            foreach ($products as $product) {
                $product->set('productFamilyId', null);
                $this->getEntityManager()->saveEntity($product);
            }
        }
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

            if ($assignedUserOwnership == 'fromProductFamily' || $ownerUserOwnership == 'fromProductFamily') {
                foreach ($entity->get('products') as $product) {
                    $toSave = false;

                    if ($assignedUserOwnership == 'fromProductFamily'
                        && ($product->get('assignedUserId') == null || $product->get('assignedUserId') == $entity->getFetched('assignedUserId'))) {
                        $product->set('assignedUserId', $entity->get('assignedUserId'));
                        $product->set('assignedUserName', $entity->get('assignedUserName'));
                        $toSave = true;
                    }

                    if ($ownerUserOwnership == 'fromProductFamily'
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
