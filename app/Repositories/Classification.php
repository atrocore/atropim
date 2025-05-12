<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Classification extends Hierarchy
{
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

        if (!$entity->isNew() && $entity->isAttributeChanged('entityId')) {
            throw new BadRequest($this->exception('entityCannotBeChanged'));
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequest(sprintf($this->exception('notUniqueValue', 'Global'), 'code'));
        }

        return $result;
    }

    public function remove(Entity $entity, array $options = [])
    {
        if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
            $this->deleteFromDb($toDelete->get('id'), true);
        }

        return parent::remove($entity, $options);
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where([
                'id!='    => $entity->get('id'),
                'release' => $entity->get('release'),
                'code'    => $entity->get('code'),
                'deleted' => $deleted
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('ClassificationAttribute')
            ->where(['classificationId' => $entity->get('id')])
            ->removeCollection();
    }

    protected function exception(string $key, string $scope = 'Attribute'): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', $scope);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
