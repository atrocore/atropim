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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ClassificationAttributeAlreadyExists;

class ClassificationAttribute extends Base
{
    /**
     * @var mixed
     */
    private $classificationAttributes = [];

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
            'channelId'   => $classificationAttribute->get('channelId')
        ];

        $result = $this
            ->getProductAttributeValueRepository()
            ->where($where)
            ->find();

        return $result;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        // for unique index
        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

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
        $this->getProductAttributeValueRepository()->validateValue($attribute, $entity);
    }

    public function save(Entity $entity, array $options = [])
    {
        $attribute = $this->getAttributeRepository()->get($entity->get('attributeId'));
        if (!empty($entity->get('channelId'))) {
            $channel = $this->getEntityManager()->getRepository('Channel')->get($entity->get('channelId'));
        }

        if ($entity->isNew()) {
            $duplicate = $this->getDuplicateEntity($entity);
            if (!empty($duplicate)) {
                return $duplicate;
            }
        }

        try {
            $result = parent::save($entity, $options);
        } catch (UniqueConstraintViolationException $e) {
            $attributeName = !empty($attribute) ? $attribute->get('name') : $entity->get('attributeId');
            $channelName = 'Global';
            if (!empty($entity->get('channelId'))) {
                $channelName = !empty($channel) ? $channel->get('name') : $entity->get('channelId');
            }

            throw new ClassificationAttributeAlreadyExists(
                sprintf($this->getInjection('language')->translate('attributeRecordAlreadyExists', 'exceptions'), $attributeName, "'$channelName'")
            );
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
                'channelId'        => $entity->get('channelId'),
                'language'         => $entity->get('language'),
                'deleted'          => $deleted,
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    public function getProductChannelsViaClassificationId(string $classificationId): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->from($this->getConnection()->quoteIdentifier('product'), 'p')
            ->leftJoin('p', 'product_classification', 'pcl', 'p.id = pcl.product_id AND pcl.deleted = :false')
            ->andWhere('pcl.classification_id = :id')
            ->andWhere('p.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('id', $classificationId)
            ->fetchFirstColumn();
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

    protected function getAttributeRepository(): Attribute
    {
        return $this->getEntityManager()->getRepository('Attribute');
    }

    protected function getProductAttributeValueRepository(): ProductAttributeValue
    {
        return $this->getEntityManager()->getRepository('ProductAttributeValue');
    }

    protected function processSpecifiedRelationsSave(Entity $entity, array $options = array())
    {
        parent::processSpecifiedRelationsSave($entity, $options);

        $attribute = $entity->get('attribute');
        if (!empty($attribute) && $attribute->get('type') == 'linkMultiple') {
            ProductAttributeValue::saveLinkMultipleValues($entity, $this);
        }
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

    public function getParentClassificationAttribute(Entity $entity): ?Entity
    {
        $cas = $this->getParentsClassificationAttributes($entity);
        if ($cas === null) {
            return null;
        }

        foreach ($cas as $ca) {
            if (
                $ca->get('attributeId') === $entity->get('attributeId')
                && ((empty($ca->get('channelId')) && empty($entity->get('channelId'))) ||
                    $ca->get('channelId') === $entity->get('channelId'))
                && $ca->get('language') === $entity->get('language')
            ) {
                return $ca;
            }
        }

        return null;
    }

    public function isClassificationAttributeRelationInherited(Entity $entity): bool
    {
        return !empty($this->getParentClassificationAttribute($entity));
    }

    public function isClassificationAttributeValueInherited(Entity $entity): ?bool
    {
        $cas = $this->getParentsClassificationAttributes($entity);
        if ($cas === null) {
            return null;
        }

        foreach ($cas as $ca) {
            if (
                $ca->get('attributeId') === $entity->get('attributeId')
                && $ca->get('channelId') === $entity->get('channelId')
                && $ca->get('language') === $entity->get('language')
                && $ca->get('isRequired') === $entity->get('isRequired')
                && $this->areCaValuesEqual($ca, $entity)
            ) {
                return true;
            }
        }

        return false;
    }

    public function areCaValuesEqual(Entity $ca1, Entity $ca2): bool
    {
        $attribute = $ca1->get('attribute');
        switch ($attribute->get('type')) {
            case 'array':
            case 'extensibleMultiEnum':
                $val1 = @json_decode((string)$ca1->getFetched('textValue'), true);
                $val2 = @json_decode((string)$ca2->getFetched('textValue'), true);
                $result = Entity::areValuesEqual(Entity::TEXT, json_encode($val1 ?? []), json_encode($val2 ?? []));
                break;
            case 'text':
            case 'wysiwyg':
                $result = Entity::areValuesEqual(Entity::TEXT, $ca1->getFetched('textValue'), $ca2->getFetched('textValue'));
                break;
            case 'bool':
                $result = Entity::areValuesEqual(Entity::BOOL, $ca1->getFetched('boolValue'), $ca2->getFetched('boolValue'));
                break;
            case 'int':
                $result = Entity::areValuesEqual(Entity::INT, $ca1->getFetched('intValue'), $ca2->getFetched('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                }
                break;
            case 'rangeInt':
                $result = Entity::areValuesEqual(Entity::INT, $ca1->getFetched('intValue'), $ca2->getFetched('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::INT, $ca1->getFetched('intValue1'), $ca2->getFetched('intValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                }
                break;
            case 'float':
                $result = Entity::areValuesEqual(Entity::FLOAT, $ca1->getFetched('floatValue'), $ca2->getFetched('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                }
                break;
            case 'rangeFloat':
                $result = Entity::areValuesEqual(Entity::FLOAT, $ca1->getFetched('floatValue'), $ca2->getFetched('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::FLOAT, $ca1->getFetched('floatValue1'), $ca2->getFetched('floatValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                }
                break;
            case 'date':
                $result = Entity::areValuesEqual(Entity::DATE, $ca1->getFetched('dateValue'), $ca2->getFetched('dateValue'));
                break;
            case 'datetime':
                $result = Entity::areValuesEqual(Entity::DATETIME, $ca1->getFetched('datetimeValue'), $ca2->getFetched('datetimeValue'));
                break;
            case 'file':
            case 'link':
            case 'extensibleEnum':
                $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                break;
            case 'varchar':
                $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('varcharValue'), $ca2->getFetched('varcharValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('referenceValue'), $ca2->getFetched('referenceValue'));
                }
                break;
            default:
                $result = Entity::areValuesEqual(Entity::VARCHAR, $ca1->getFetched('varcharValue'), $ca2->getFetched('varcharValue'));
                break;
        }

        return $result;
    }

    public function getParentsClassificationAttributes(Entity $entity): ?EntityCollection
    {
        if (isset($this->classificationAttributes[$entity->get('classificationId')])) {
            return $this->classificationAttributes[$entity->get('classificationId')];
        }

        $res = $this->getConnection()->createQueryBuilder()
            ->select('ca.id')
            ->from($this->getConnection()->quoteIdentifier('classification_attribute'), 'ca')
            ->where(
                "ca.classification_id IN (SELECT ph.parent_id FROM {$this->getConnection()->quoteIdentifier('classification_hierarchy')} ph WHERE ph.deleted = :false AND ph.entity_id = :classificationId)"
            )
            ->andWhere('ca.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('classificationId', $entity->get('classificationId'), Mapper::getParameterType($entity->get('classificationId')))
            ->fetchAllAssociative();

        $ids = array_column($res, 'id');

        $this->classificationAttributes[$entity->get('classificationId')] = empty($ids) ? null : $this->where(['id' => $ids])->find();

        return $this->classificationAttributes[$entity->get('classificationId')];
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
    {
        $ca = $this->get($parentId);
        if (empty($ca) || empty($ca->get('classificationId'))) {
            return [];
        }

        $classifications = $this->getEntityManager()->getRepository('Classification')->getChildrenArray($ca->get('classificationId'));

        if (empty($classifications)) {
            return [];
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('ca.*')
            ->from($this->getConnection()->quoteIdentifier('classification_attribute'), 'ca')
            ->where('ca.deleted = :false')
            ->andWhere('ca.classification_id IN (:classificationsIds)')
            ->andWhere('ca.attribute_id = :attributeId')
            ->andWhere('ca.language = :language')
            ->andWhere('ca.channel_id = :channelId')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('classificationsIds', array_column($classifications, 'id'), Connection::PARAM_STR_ARRAY)
            ->setParameter('attributeId', $ca->get('attributeId'))
            ->setParameter('language', $ca->get('language'))
            ->setParameter('channelId', $ca->get('channelId'));

        $cas = $qb->fetchAllAssociative();

        $result = [];
        foreach ($cas as $record) {
            foreach ($classifications as $classification) {
                if ($classification['id'] === $record['classification_id']) {
                    $record['childrenCount'] = $classification['childrenCount'];
                    break 1;
                }
            }
            $result[] = $record;
        }

        return $result;
    }
}
