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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Atro\Core\Exceptions\Error;
use Espo\ORM\IEntity;

class Attribute extends Base
{
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
        $this->addDependency('container');
    }

    public function prepareAllParentsCompositeAttributesIds(string $attributeId, array &$ids = []): void
    {
        $attribute = $this->get($attributeId);
        if (!empty(!empty($attribute->get('compositeAttributeId')))) {
            $ids[] = $attribute->get('compositeAttributeId');
            $this->prepareAllParentsCompositeAttributesIds($attribute->get('compositeAttributeId'), $ids);
        }
    }

    public function prepareAllChildrenCompositeAttributesIds(string $attributeId, array &$ids = []): void
    {
        $children = $this
            ->where([
                'compositeAttributeId' => $attributeId,
                'type'                 => 'composite'
            ])
            ->find();

        foreach ($children as $child) {
            $ids[] = $child->get('id');
            $this->prepareAllChildrenCompositeAttributesIds($child->get('id'), $ids);
        }
    }

    public function prepareAllChildrenAttributesIdsForComposite(string $attributeId, array &$ids = []): void
    {
        $children = $this
            ->where(['compositeAttributeId' => $attributeId])
            ->find();

        foreach ($children as $child) {
            $ids[] = $child->get('id');
            if ($child->get('type') === 'composite') {
                $this->prepareAllChildrenAttributesIdsForComposite($child->get('id'), $ids);
            }
        }
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->setCacheData('attribute_product_fields', null);
    }

    public function addAttributeValueForComposite(Entity $entity): void
    {
        if (empty($entity->get('compositeAttributeId'))) {
            return;
        }

        $attributesIds = [$entity->get('compositeAttributeId')];
        $this->prepareAllParentsCompositeAttributesIds($entity->get('compositeAttributeId'), $attributesIds);

        $name = Util::toUnderScore(lcfirst($entity->get('entityId')));

        $avIds = $this->getConnection()->createQueryBuilder()
            ->select("{$name}_id")
            ->distinct()
            ->from("{$name}_attribute_value")
            ->where('attribute_id IN (:attributesIds)')
            ->setParameter('attributesIds', $attributesIds, $this->getConnection()::PARAM_STR_ARRAY)
            ->fetchFirstColumn();

        foreach ($avIds as $avId) {
            try {
                $this->addAttributeValue($entity->get('entityId'), $avId, $entity->get('id'));
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function addAttributeValue(string $entityName, string $entityId, string $attributeId): void
    {
        $attribute = $this->get($attributeId);
        if (empty($attribute)) {
            throw new NotFound();
        }

        if ($attribute->get('entityId') !== $entityName) {
            throw new BadRequest($this->exception('attributeNotBelongToEntity'));
        }

        $name = Util::toUnderScore(lcfirst($entityName));

        $this->getConnection()->createQueryBuilder()
            ->insert("{$name}_attribute_value")
            ->setValue('id', ':id')
            ->setValue('attribute_id', ':attributeId')
            ->setValue("{$name}_id", ':entityId')
            ->setParameter('id', Util::generateId())
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId)
            ->executeQuery();

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => 'Relate',
            'parentType' => $entityName,
            'parentId'   => $entityId,
            'data'       => [
                'relatedType' => 'Attribute',
                'relatedId'   => $attributeId,
                'link'        => lcfirst($entityName) . "AttributeValues"
            ],
        ]);
        $this->getEntityManager()->saveEntity($note);
    }

    public function upsertAttributeValue(IEntity $entity, string $fieldName, $value): void
    {
        $name = Util::toUnderScore(lcfirst($entity->getEntityName()));
        $valColumn = $entity->fields[$fieldName]['column'];

        if (Converter::isPgSQL($this->getConnection())) {
            $sql = "INSERT INTO {$name}_attribute_value (id, {$name}_id, attribute_id, $valColumn) VALUES (:id, :entityId, :attributeId, :value) ON CONFLICT (deleted, {$name}_id, attribute_id) DO UPDATE SET $valColumn = EXCLUDED.$valColumn";
        } else {
            $sql = "INSERT INTO {$name}_attribute_value (id, {$name}_id, attribute_id, $valColumn) VALUES (:id, :entityId, :attributeId, :value) ON DUPLICATE KEY UPDATE $valColumn = VALUES($valColumn)";
        }

        $stmt = $this->getEntityManager()->getPDO()->prepare($sql);

        $stmt->bindValue(':id', Util::generateId());
        $stmt->bindValue(':entityId', $entity->id);
        $stmt->bindValue(':attributeId', $entity->fields[$fieldName]['attributeId']);

        if ($entity->fields[$fieldName]['type'] === 'bool') {
            $stmt->bindValue(':value', $value, \PDO::PARAM_BOOL);
        } else {
            $stmt->bindValue(':value', $value);
        }

        $stmt->execute();
    }

    public function hasAttributeValue(string $entityName, string $entityId, string $attributeId): bool
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from("{$name}_attribute_value")
            ->where('attribute_id=:attributeId')
            ->andWhere("{$name}_id=:entityId")
            ->andWhere('deleted=:false')
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        return !empty($res);
    }

    public function removeAttributeValue(string $entityName, string $entityId, string $attributeId): bool
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from("{$name}_attribute_value")
            ->where('attribute_id=:attributeId')
            ->andWhere("{$name}_id=:entityId")
            ->andWhere('deleted=:false')
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($res)) {
            $this->getConnection()->createQueryBuilder()
                ->delete("{$name}_attribute_value")
                ->where('attribute_id=:attributeId')
                ->andWhere("{$name}_id=:entityId")
                ->setParameter('attributeId', $attributeId)
                ->setParameter('entityId', $entityId)
                ->executeQuery();

            $note = $this->getEntityManager()->getEntity('Note');
            $note->set([
                'type'       => 'Unrelate',
                'parentType' => $entityName,
                'parentId'   => $entityId,
                'data'       => [
                    'relatedType' => 'Attribute',
                    'relatedId'   => $attributeId,
                    'link'        => lcfirst($entityName) . "AttributeValues"
                ],
            ]);
            $this->getEntityManager()->saveEntity($note);
        }

        return true;
    }

    public function updateSortOrderInAttributeGroup(array $ids): void
    {
        $rows = $this->getConnection()->createQueryBuilder()
            ->from($this->getConnection()->quoteIdentifier('attribute'))
            ->select('sort_order', 'id')
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, Mapper::getParameterType($ids))
            ->orderBy('sort_order')
            ->fetchAllAssociative();

        $values = array_filter(array_unique(array_column($rows, 'sort_order')));
        if (count($values) === count($rows)) {
            // shuffle orders
            foreach ($ids as $k => $id) {
                $value = $rows[$k]['sort_order'];
                $this->getConnection()->createQueryBuilder()
                    ->update($this->getConnection()->quoteIdentifier('attribute'), 'a')
                    ->set('sort_order', ':sortOrder')
                    ->where('a.id = :id')
                    ->setParameter('sortOrder', $value, ParameterType::INTEGER)
                    ->setParameter('id', $id)
                    ->executeQuery();
            }
        } else {
            $min = min($values) ?? 0;

            foreach ($ids as $k => $id) {
                $this->getConnection()->createQueryBuilder()
                    ->update($this->getConnection()->quoteIdentifier('attribute'), 'a')
                    ->set('sort_order', ':sortOrder')
                    ->where('a.id = :id')
                    ->setParameter('sortOrder',
                        $min + $k * 10)
                    ->setParameter('id', $id)
                    ->executeQuery();
            }
        }
    }

    public function getMultilingualAttributeTypes(): array
    {
        $attributes = [];
        foreach ($this->getMetadata()->get(['attributes'], []) as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('entityId')) {
            throw new BadRequest($this->exception('entityCannotBeChanged'));
        }

        if (!in_array($entity->get('type'), $this->getMultilingualAttributeTypes())) {
            $entity->set('isMultilang', false);
        }

        if ($entity->get('sortOrder') === null) {
            $connection = $this->getEntityManager()->getConnection();
            $max = $connection->createQueryBuilder()
                ->select('MAX(sort_order)')
                ->from($connection->quoteIdentifier('attribute'))
                ->fetchOne();

            if (empty($max)) {
                $max = 0;
            }
            $entity->set('sortOrder', $max + 10);
        }

        if (!empty($entity->get('compositeAttributeId'))) {
            if ($entity->get('compositeAttributeId') === $entity->get('id')) {
                throw new BadRequest($this->exception('compositeAttributeCannotBeChild'));
            }

            $composite = $this->get($entity->get('compositeAttributeId'));
            if (empty($composite) || $composite->get('type') !== 'composite') {
                throw new BadRequest($this->exception('compositeAttributeNotFound'));
            }

            if ($composite->get('entityId') !== $entity->get('entityId')) {
                throw new BadRequest($this->exception('attributeEntityMustBeSameAsInCompositeAttribute'));
            }

            while (!empty($composite->get('compositeAttributeId'))) {
                if ($composite->get('id') === $entity->get('id')) {
                    throw new BadRequest($this->exception('compositeAttributeCannotBeChild'));
                }
                $composite = $this->get($composite->get('compositeAttributeId'));
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('unique') && $entity->get('unique')) {
            $qb = $this->getConnection()->createQueryBuilder();

            $qb
                ->select('COUNT(*)')
                ->from('product_attribute_value')
                ->where('attribute_id = :attributeId')
                ->andWhere('deleted = :false')
                ->setParameter('attributeId', $entity->id)
                ->setParameter('false', false, Mapper::getParameterType(false));

            switch ($entity->get('type')) {
                case 'float':
                    $qb->andWhere('float_value IS NOT NULL');
                    $qb->groupBy("float_value, {$this->getConnection()->quoteIdentifier('language')}, scope, channel_id");
                    break;
                case 'int':
                    $qb->andWhere('int_value IS NOT NULL');
                    $qb->groupBy("int_value, {$this->getConnection()->quoteIdentifier('language')}, scope, channel_id");
                case 'date':
                    $qb->andWhere('date_value IS NOT NULL');
                    $qb->groupBy("date_value, {$this->getConnection()->quoteIdentifier('language')}, scope, channel_id");
                case 'datetime':
                    $qb->andWhere('datetime_value IS NOT NULL');
                    $qb->groupBy("datetime_value, {$this->getConnection()->quoteIdentifier('language')}, scope, channel_id");
                    break;
                default:
                    $qb->andWhere('varchar_value IS NOT NULL');
                    $qb->groupBy("varchar_value, {$this->getConnection()->quoteIdentifier('language')}, scope, channel_id");
                    break;
            }

            $qb->having('COUNT(*) > 1');

            if (!empty($qb->fetchAssociative())) {
                throw new Error($this->exception('attributeNotHaveUniqueValue'));
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('pattern') && !empty($pattern = $entity->get('pattern'))) {
            if (!preg_match("/^\/(.*)\/$/", $pattern)) {
                throw new BadRequest($this->getInjection('language')->translate('regexNotValid', 'exceptions',
                    'FieldManager'));
            }

            $res = $this->getConnection()->createQueryBuilder()
                ->select('varchar_value')
                ->from('product_attribute_value')
                ->where('attribute_id = :attributeId')
                ->andWhere('deleted = :false')
                ->andWhere('varchar_value IS NOT NULL')
                ->andWhere("varchar_value != ''")
                ->setParameter('attributeId', $entity->get('id'))
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->fetchAllAssociative();

            foreach ($res as $row) {
                if (!preg_match($pattern, $row['varchar_value'])) {
                    throw new BadRequest($this->exception('someAttributeDontMathToPattern'));
                }
            }
        }

        if (in_array($entity->get('type'), ['wysiwyg', 'markdown', 'text', 'composite'])) {
            $entity->set('fullWidth', true);
        }

        parent::beforeSave($entity, $options);

        $this->validateMinMax($entity);
    }

    public function validateMinMax(Entity $entity): void
    {
        if (
            ($entity->isAttributeChanged('max') || $entity->isAttributeChanged('min'))
            && $entity->get('min') !== null
            && $entity->get('max') !== null
            && $entity->get('max') < $entity->get('min')
        ) {
            throw new BadRequest($this->getInjection('language')->translate('maxLessThanMin', 'exceptions',
                'Attribute'));
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
                $converterName = $this->getMetadata()->get([
                    'attributes',
                    $entity->getFetched('type'),
                    'convert',
                    $entity->get('type')
                ]);
                if (empty($converterName)) {
                    $message = $this->getInjection('language')->translate('noAttributeConverterFound', 'exceptions',
                        'Attribute');
                    throw new BadRequest(sprintf($message, $entity->getFetched('type'), $entity->get('type')));
                }
                $this->getInjection('container')->get($converterName)->convert($entity);
            }

            if (!$entity->isNew() && $entity->isAttributeChanged('measureId')) {
                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('reference_value', ':null')
                    ->where('attribute_id = :id')
                    ->setParameter('null', null)
                    ->setParameter('id', $entity->get('id'))
                    ->executeQuery();
            }

            $result = parent::save($entity, $options);
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew() && !empty($entity->get('compositeAttributeId'))) {
            $this->addAttributeValueForComposite($entity);
        }

        /**
         * Delete all lingual product attribute values
         */
        if (!$entity->isNew() && $entity->isAttributeChanged('isMultilang') && empty($entity->get('isMultilang'))) {
            while (true) {
                $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')
                    ->where(['attributeId' => $entity->get('id'), 'language!=' => 'main'])
                    ->limit(0, 2000)
                    ->order('createdAt', 'ASC')
                    ->find();
                if (empty($pavs[0])) {
                    break;
                }
                foreach ($pavs as $pav) {
                    $this->getEntityManager()->removeEntity($pav);
                }
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('notNull') && !empty($entity->get('notNull'))) {
            $attributeId = $entity->get('id');
            $query = $this->getEntityManager()
                ->getConnection()->createQueryBuilder()
                ->update('product_attribute_value')
                ->where("attribute_id=:attributeId")
                ->andWhere('deleted=:false')
                ->setParameter('attributeId', $attributeId, Mapper::getParameterType($attributeId))
                ->setParameter('false', false, ParameterType::BOOLEAN);

            if ($entity->get('type') === 'varchar') {
                $query
                    ->set('varchar_value', ':empty')
                    ->andWhere("varchar_value is NULL")
                    ->setParameter('empty', '', ParameterType::STRING)
                    ->executeQuery();
            }

            if (in_array($entity->get('type'), ['text', 'markdown', 'wysiwyg', 'url'])) {
                $query
                    ->set('text_value', ':empty')
                    ->andWhere("text_value is NULL")
                    ->setParameter('empty', '', ParameterType::STRING)
                    ->executeQuery();
            }

            if ($entity->get('type') === 'int') {
                $query
                    ->set('int_value', ':zero')
                    ->andWhere("int_value is NULL")
                    ->setParameter('zero', 0, ParameterType::INTEGER)
                    ->executeQuery();
            }

            if ($entity->get('type') === 'float') {
                $query
                    ->set('float_value', ':zero')
                    ->andWhere("float_value is NULL")
                    ->setParameter('zero', 0, ParameterType::INTEGER)
                    ->executeQuery();
            }

            if ($entity->get('type') === 'bool') {
                $query->set('bool_value', ':defaultBool')
                    ->where("bool_value is NULL")
                    ->setParameter('defaultBool', false, ParameterType::BOOLEAN)
                    ->executeQuery();
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('ProductAttributeValue')->removeByAttributeId($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()
            ->createQueryBuilder()
            ->update('product_attribute_value')
            ->set('deleted', ':false')
            ->where('attribute_id = :attributeId')
            ->andWhere('deleted = :true')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('attributeId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('true', true, ParameterType::BOOLEAN);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, "exceptions", "Attribute");
    }
}
