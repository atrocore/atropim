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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Classification extends Hierarchy
{
    public function getLinkedAttributesIds(string $id, string $scope = 'Global'): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->select(['attributeId'])
            ->where(['classificationId' => $id, 'scope' => $scope])
            ->find()
            ->toArray();

        return array_column($data, 'attributeId');
    }

    public function getLinkedWithAttributeGroup(array $classificationsIds, ?string $attributeGroupId): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->select(['id'])
            ->distinct()
            ->join('attribute')
            ->where(
                [
                    'classificationId'           => $classificationsIds,
                    'attribute.attributeGroupId' => ($attributeGroupId != '') ? $attributeGroupId : null
                ]
            )
            ->find()
            ->toArray();

        return array_column($data, 'id');
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1062') === false) {
                throw $e;
            }
            throw new BadRequest(sprintf($this->getInjection('language')->translate('notUniqueValue', 'exceptions', 'Global'), 'code'));
        }

        return $result;
    }

    public function remove(Entity $entity, array $options = [])
    {
        try {
            $result = parent::remove($entity, $options);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1062') === false) {
                throw $e;
            }
            if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
                $this->deleteFromDb($toDelete->get('id'), true);
            }
            return parent::remove($entity, $options);
        }

        return $result;
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where(['id!=' => $entity->get('id'), 'release' => $entity->get('release'), 'code' => $entity->get('code'), 'deleted' => $deleted])
            ->findOne(['withDeleted' => $deleted]);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('ClassificationAttribute')
            ->where(['classificationId' => $entity->get('id')])
            ->removeCollection();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName === 'products') {
            $this->getEntityManager()->getRepository('Product')->relateClassification($foreign, $entity);
        }

        parent::afterRelate($entity, $relationName, $foreign, $data, $options);
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'products') {
            $this->getEntityManager()->getRepository('Product')->unRelateClassification($foreign, $entity);
        }

        parent::afterUnrelate($entity, $relationName, $foreign, $options);
    }
}
