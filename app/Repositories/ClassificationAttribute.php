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

use Espo\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ClassificationAttributeAlreadyExists;

class ClassificationAttribute extends Relationship
{
    public function getInheritedPavs(string $id): EntityCollection
    {
        $result = new EntityCollection([], 'ProductAttributeValue');

        $classificationAttribute = $this->get($id);
        if (empty($classificationAttribute)) {
            return $result;
        }

        $classification = $this->getEntityManager()->getRepository('Classification')->get($classificationAttribute->get('classificationId'));
        if (empty($classification)) {
            return $result;
        }

        $productsIds = $classification->getLinkMultipleIdList('products');
        if (empty($productsIds)) {
            return $result;
        }

        $where = [
            'productId'   => $productsIds,
            'attributeId' => $classificationAttribute->get('attributeId'),
            'language'    => $classificationAttribute->get('language'),
            'scope'       => $classificationAttribute->get('scope'),
        ];

        if ($classificationAttribute->get('scope') === 'Channel') {
            $where['channelId'] = $classificationAttribute->get('channelId');
        }

        $result = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where($where)
            ->find();

        return $result;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('scope') === 'Global') {
            $entity->set('channelId', '');
        }

        $type = $entity->get('attribute')->get('type');

        if($type === 'rangeInt'){
            $this->getEntityManager()->getRepository('Attribute')
                ->checkMinMaxIntValue($type, $entity->get('min'), $entity->get('max'));
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (\Throwable $e) {
            // if duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                if ($entity->isNew()) {
                    return $this->getDuplicateEntity($entity);
                }
                $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));
                $attributeName = !empty($attribute) ? $attribute->get('name') : $entity->get('attributeId');

                $channelName = $entity->get('scope');
                if ($channelName === 'Channel') {
                    $channel = $this->getEntityManager()->getRepository('Channel')->get($entity->get('channelId'));
                    $channelName = !empty($channel) ? $channel->get('name') : $entity->get('channelId');
                }

                throw new ClassificationAttributeAlreadyExists(
                    sprintf($this->getInjection('language')->translate('attributeRecordAlreadyExists', 'exceptions'), $attributeName, "'$channelName'")
                );
            }

            throw $e;
        }

        return $result;
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where([
                'id!='             => $entity->get('id'),
                'classificationId' => $entity->get('classificationId'),
                'attributeId'      => $entity->get('attributeId'),
                'scope'            => $entity->get('scope'),
                'channelId'        => $entity->get('channelId'),
                'language'         => $entity->get('language'),
                'deleted'          => $deleted,
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    public function remove(Entity $entity, array $options = [])
    {
        try {
            $result = parent::remove($entity, $options);
        } catch (\Throwable $e) {
            // delete duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
                    $this->deleteFromDb($toDelete->get('id'), true);
                }
                return parent::remove($entity, $options);
            }
            throw $e;
        }

        return $result;
    }

    public function getProductChannelsViaClassificationId(string $classificationId): array
    {
        $classificationId = $this->getPDO()->quote($classificationId);

        $sql = "SELECT p.id
                FROM product p 
                LEFT JOIN product_classification pcl on p.id = pcl.product_id AND pcl.deleted = 0 
                WHERE pcl.classification_id=$classificationId AND p.deleted = 0";

        return $this->getPDO()->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        $this->addDependency('language');
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, string $scope = 'ClassificationAttribute'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions');
    }
}
