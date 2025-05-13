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

use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Core\ValueConverter;

class ClassificationAttribute extends AbstractAttributeValue
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->validateClassificationAttribute($entity);
    }

    public function validateClassificationAttribute(Entity $entity): void
    {
        $attribute = $this->getAttributeRepository()->get($entity->get('attributeId'));
        if (empty($attribute)) {
            throw new BadRequest("No Attribute '{$entity->get('attributeId')}' has been found.");
        }

        $this->getAttributeRepository()->validateMinMax($entity);
    }

    public function save(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $duplicate = $this->getDuplicateEntity($entity);
            if (!empty($duplicate)) {
                return $duplicate;
            }
        }

        return parent::save($entity, $options);
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where([
                'id!='             => $entity->get('id'),
                'classificationId' => $entity->get('classificationId'),
                'attributeId'      => $entity->get('attributeId'),
                'deleted'          => $deleted,
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    protected function init()
    {
        $this->addDependency('language');
        $this->addDependency(ValueConverter::class);
    }

    protected function translate(string $key, string $label, string $scope = 'ClassificationAttribute'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions');
    }

    protected function getAttributeRepository(): Attribute
    {
        return $this->getEntityManager()->getRepository('Attribute');
    }

    protected function getOwnNotificationMessageData(Entity $entity): array
    {
        return [
            'entityName' => $entity->get('attribute')->get('name'),
            'entityType' => $entity->getEntityType(),
            'entityId'   => $entity->get('id'),
            'changedBy'  => $this->getEntityManager()->getUser()->get('id')
        ];
    }
}
