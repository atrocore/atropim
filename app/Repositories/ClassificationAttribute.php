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

use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ClassificationAttribute extends Base
{
    public function getClassificationRelatedRecords(Entity $classification): array
    {
        $fieldName = lcfirst($classification->get('entityId')) . 'Id';
        $columnName = Util::toUnderScore($fieldName);

        $tableName = Util::toUnderScore(lcfirst($classification->get('entityId')));

        return $this->getConnection()->createQueryBuilder()
            ->select("t.id")
            ->from("{$tableName}_classification", 'r')
            ->innerJoin('r', $this->getConnection()->quoteIdentifier($tableName), 't',
                "t.id=r.$columnName AND t.deleted=:false")
            ->where('r.deleted=:false')
            ->andWhere('r.classification_id=:classificationId')
            ->setParameter('classificationId', $classification->get('id'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();
    }

    public function getClassificationDataForClassificationAttributeId(string $classificationAttributeId): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('c.id as classification_id, c.entity_id, ca.attribute_id')
            ->from('classification', 'c')
            ->innerJoin('c', 'classification_attribute', 'ca', 'c.id = ca.classification_id AND ca.deleted=:false')
            ->where('c.deleted=:false')
            ->andWhere('ca.id=:id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $classificationAttributeId)
            ->fetchAssociative();
    }

    public function deleteAttributeValuesByClassificationAttribute(
        string $entityName,
        string $attributeId,
        string $classificationId
    ): void {
        $tableName = Util::toUnderScore(lcfirst($entityName));
        $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->delete("{$tableName}_attribute_value")
            ->where("attribute_id=:attributeId")
            ->andWhere("{$tableName}_id IN (SELECT {$tableName}_id FROM {$tableName}_classification WHERE classification_id=:classificationId AND deleted=:false)")
            ->setParameter('attributeId', $attributeId)
            ->setParameter('classificationId', $classificationId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->validateClassificationAttribute($entity);

        // prepare default value
        $data = $entity->get('data') ?? [];
        $data = json_decode(json_encode($data), true);
        foreach (['value', 'valueFrom', 'valueTo', 'valueUnitId', 'valueId', 'valueIds'] as $key) {
            if ($entity->has($key)) {
                $data['default'][$key] = $entity->get($key);
            }
        }
        $entity->set('data', $data);
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
