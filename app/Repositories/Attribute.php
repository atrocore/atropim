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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;

class Attribute extends Hierarchy
{
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
        $this->addDependency('container');
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->setCacheData('attribute_product_fields', null);
    }

    public function updateSortOrderInAttributeGroup(array $ids): void
    {
        foreach ($ids as $k => $id) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('attribute'), 'a')
                ->set('sort_order_in_attribute_group', ':sortOrder')
                ->where('a.id = :id')
                ->setParameter('sortOrder', $k * 10)
                ->setParameter('id', $id)
                ->executeQuery();
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

        if (!in_array($entity->get('type'), $this->getMultilingualAttributeTypes())) {
            $entity->set('isMultilang', false);
        }

        if ($entity->get('sortOrderInProduct') === null) {
            $entity->set('sortOrderInProduct', time());
        }

        if ($entity->get('sortOrderInAttributeGroup') === null) {
            $entity->set('sortOrderInAttributeGroup', time());
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
                throw new BadRequest($this->getInjection('language')->translate('regexNotValid', 'exceptions', 'FieldManager'));
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
            throw new BadRequest($this->getInjection('language')->translate('maxLessThanMin', 'exceptions', 'Attribute'));
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
                $converterName = $this->getMetadata()->get(['attributes', $entity->getFetched('type'), 'convert', $entity->get('type')]);
                if (empty($converterName)) {
                    $message = $this->getInjection('language')->translate('noAttributeConverterFound', 'exceptions', 'Attribute');
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
        if ($entity->isAttributeChanged('virtualProductField') || (!empty($entity->get('virtualProductField') && $entity->isAttributeChanged('code')))) {
            $this->clearCache();
        }

        parent::afterSave($entity, $options);

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

            if($entity->get('type') === 'varchar'){
                $query
                    ->set('varchar_value', ':empty')
                    ->andWhere("varchar_value is NULL")
                    ->setParameter('empty', '', ParameterType::STRING)
                    ->executeQuery();
            }

            if(in_array($entity->get('type'), ['text', 'markdown', 'wysiwyg', 'url'])){
                $query
                    ->set('text_value', ':empty')
                    ->andWhere("text_value is NULL")
                    ->setParameter('empty', '', ParameterType::STRING)
                    ->executeQuery();
            }

            if($entity->get('type') === 'int'){
                $query
                    ->set('int_value', ':zero')
                    ->andWhere("int_value is NULL")
                    ->setParameter('zero', 0, ParameterType::INTEGER)
                    ->executeQuery();
            }

            if($entity->get('type') === 'float'){
                $query
                    ->set('float_value', ':zero')
                    ->andWhere("float_value is NULL")
                    ->setParameter('zero', 0, ParameterType::INTEGER)
                    ->executeQuery();
            }

            if($entity->get('type') === 'bool'){
                $query->set('bool_value', ':defaultBool')
                    ->where("bool_value is NULL")
                    ->setParameter('defaultBool', false, ParameterType::BOOLEAN)
                    ->executeQuery();
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('virtualProductField'))) {
            $this->clearCache();
        }

        $this->getEntityManager()->getRepository('ProductAttributeValue')->removeByAttributeId($entity->get('id'));
        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()
            ->createQueryBuilder()
            ->update('product_attribute_value')
            ->set('deleted',':false')
            ->where('attribute_id = :attributeId')
            ->andWhere('deleted = :true')
            ->setParameter('false',false, ParameterType::BOOLEAN)
            ->setParameter('attributeId',$entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('true',true, ParameterType::BOOLEAN);
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
