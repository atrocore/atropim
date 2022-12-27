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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Espo\Core\Utils\Util;

class ProductAttributeValue extends AbstractRepository
{
    protected static $beforeSaveData = [];

    protected array $channelLanguages = [];

    protected array $productPavs = [];

    private array $pavsAttributes = [];

    public function getPavsWithAttributeGroupsData(string $productId, string $tabId, string $language): array
    {
        // prepare tabId
        if ($tabId === 'null') {
            $tabId = null;
        }

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('pav.id, pav.attribute_id, pav.scope, pav.channel_id, c.name as channel_name, pav.language')
            ->from('product_attribute_value', 'pav')
            ->leftJoin('pav', 'channel', 'c', 'pav.channel_id=c.id AND c.deleted=0')
            ->where('pav.deleted=0')
            ->andWhere('pav.product_id=:productId')->setParameter('productId', $productId);
        if (empty($tabId)) {
            $qb->andWhere('pav.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id IS NULL AND deleted=0)');
        } else {
            $qb->andWhere('pav.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id=:tabId AND deleted=0)')->setParameter('tabId', $tabId);
        }

        $pavs = $qb->fetchAllAssociative();

        if (empty($pavs)) {
            return [];
        }

        $attrsIds = array_values(array_unique(array_column($pavs, 'attribute_id')));

        // prepare suffix
        $languageSuffix = '';
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            if (in_array($language, $this->getConfig()->get('inputLanguageList', []))) {
                $languageSuffix = '_' . strtolower($language);
            }
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('a.*, ag.name' . $languageSuffix . ' as attribute_group_name, ag.sort_order as attribute_group_sort_order')
            ->from('attribute', 'a')
            ->where('a.deleted=0')
            ->leftJoin('a', 'attribute_group', 'ag', 'a.attribute_group_id=ag.id AND ag.deleted=0')
            ->andWhere('a.id IN (:attributesIds)')->setParameter('attributesIds', $attrsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);

        try {
            $attrs = $qb->fetchAllAssociative();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Getting attributes failed: ' . $e->getMessage());
            return [];
        }

        foreach ($pavs as $k => $pav) {
            foreach ($attrs as $attr) {
                if ($attr['id'] === $pav['attribute_id']) {
                    $pavs[$k]['attribute_data'] = $attr;
                    break 1;
                }
            }
        }

        return $pavs;
    }

    public function getPavAttribute(Entity $entity): ?\Pim\Entities\Attribute
    {
        if (!isset($this->pavsAttributes[$entity->get('attributeId')])) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            if (empty($attribute)) {
                $this->getPDO()->exec("DELETE FROM `product_attribute_value` WHERE id='{$entity->get('id')}'");
                return null;
            }
            $this->pavsAttributes[$entity->get('attributeId')] = $attribute;
        }

        return $this->pavsAttributes[$entity->get('attributeId')];
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null): array
    {
        $pav = $this->get($parentId);
        if (empty($pav) || empty($pav->get('productId'))) {
            return [];
        }

        $products = $this->getEntityManager()->getRepository('Product')->getChildrenArray($pav->get('productId'));

        if (empty($products)) {
            return [];
        }

        $query = "SELECT *
                  FROM `product_attribute_value`
                  WHERE deleted=0
                    AND product_id IN ('" . implode("','", array_column($products, 'id')) . "')
                    AND attribute_id='{$pav->get('attributeId')}'
                    AND `language`='{$pav->get('language')}'
                    AND scope='{$pav->get('scope')}'";

        if ($pav->get('scope') === 'Channel') {
            $query .= " AND channel_id='{$pav->get('channelId')}'";
        }

        $result = [];
        foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            foreach ($products as $product) {
                if ($product['id'] === $record['product_id']) {
                    $record['childrenCount'] = $product['childrenCount'];
                    break 1;
                }
            }
            $result[] = $record;
        }

        return $result;
    }

    public function getParentPav(Entity $entity): ?Entity
    {
        $pavs = $this->getParentsPavs($entity);
        if ($pavs === null) {
            return null;
        }

        foreach ($pavs as $pav) {
            if (
                $pav->get('attributeId') === $entity->get('attributeId')
                && $pav->get('scope') === $entity->get('scope')
                && $pav->get('language') === $entity->get('language')
            ) {
                if ($pav->get('scope') === 'Global') {
                    return $pav;
                }

                if ($pav->get('channelId') === $entity->get('channelId')) {
                    return $pav;
                }
            }
        }

        return null;
    }

    public function isPavRelationInherited(Entity $entity): bool
    {
        return !empty($this->getParentPav($entity));
    }

    public function isPavValueInherited(Entity $entity): ?bool
    {
        $pav = $this->getParentPav($entity);
        if (empty($pav)) {
            return null;
        }

        return $this->arePavsValuesEqual($pav, $entity);
    }

    public function arePavsValuesEqual(Entity $pav1, Entity $pav2): bool
    {
        switch ($pav1->get('attributeType')) {
            case 'array':
            case 'multiEnum':
            case 'text':
            case 'wysiwyg':
                $result = Entity::areValuesEqual(Entity::TEXT, $pav1->get('textValue'), $pav2->get('textValue'));
                break;
            case 'bool':
                $result = Entity::areValuesEqual(Entity::BOOL, $pav1->get('boolValue'), $pav2->get('boolValue'));
                break;
            case 'currency':
            case 'unit':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
                break;
            case 'int':
                $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue'), $pav2->get('intValue'));
                break;
            case 'float':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                break;
            case 'date':
                $result = Entity::areValuesEqual(Entity::DATE, $pav1->get('dateValue'), $pav2->get('dateValue'));
                break;
            case 'datetime':
                $result = Entity::areValuesEqual(Entity::DATETIME, $pav1->get('datetimeValue'), $pav2->get('datetimeValue'));
                break;
            default:
                $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                break;
        }

        return $result;
    }

    public function getParentsPavs(Entity $entity): ?EntityCollection
    {
        if (isset($this->productPavs[$entity->get('productId')])) {
            return $this->productPavs[$entity->get('productId')];
        }

        $query = "SELECT id
                  FROM `product_attribute_value`
                  WHERE product_id IN (SELECT parent_id FROM `product_hierarchy` WHERE deleted=0 AND entity_id='{$entity->get('productId')}')
                    AND deleted=0";

        $ids = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN);

        $this->productPavs[$entity->get('productId')] = empty($ids) ? null : $this->where(['id' => $ids])->find();

        return $this->productPavs[$entity->get('productId')];
    }

    public function isInheritedFromPf(Entity $pav): bool
    {
        $productFamilyId = $this
            ->getPDO()
            ->query("SELECT product_family_id FROM `product` WHERE id='{$pav->get('productId')}'")
            ->fetch(\PDO::FETCH_COLUMN);

        if (empty($productFamilyId)) {
            return false;
        }

        $isRequired = !empty($pav->get('isRequired')) ? 1 : 0;

        $query = "SELECT pfa.id
                  FROM `product_family_attribute` pfa
                  LEFT JOIN `product_family` pf ON pf.id=pfa.product_family_id
                  WHERE pfa.deleted=0
                    AND pf.deleted=0
                    AND pf.id='$productFamilyId'
                    AND pfa.is_required=$isRequired
                    AND pfa.attribute_id='{$pav->get('attributeId')}'
                    AND pfa.scope='{$pav->get('scope')}'";

        if ($pav->get('scope') === 'Channel') {
            $query .= " AND pfa.channel_id='{$pav->get('channelId')}'";
        }

        return !empty($this->getPDO()->query($query)->fetch(\PDO::FETCH_COLUMN));
    }

    public function convertValue(Entity $entity): void
    {
        if (empty($entity->get('attributeType'))) {
            return;
        }

        if (in_array($entity->get('attributeType'), ['enum', 'multiEnum'])) {
            if (empty($attribute = $this->getPavAttribute($entity))) {
                return;
            }

            $entity->set('typeValue', $this->getAttributeOptions($attribute, $entity->get('language')));
            $entity->set('typeValueIds', $this->getAttributeOptionsIds($attribute));
        }

        switch ($entity->get('attributeType')) {
            case 'array':
            case 'multiEnum':
                $entity->set('value', null);
                $entity->set('valueOptionsIds', @json_decode((string)$entity->get('textValue'), true));

                if ($entity->get('valueOptionsIds') !== null && is_array($entity->get('valueOptionsIds'))) {
                    $values = [];
                    foreach ($entity->get('valueOptionsIds') as $optionId) {
                        $key = array_search($optionId, $entity->get('typeValueIds'));
                        if ($key !== false) {
                            $values[] = $entity->get('typeValue')[$key];
                        }

                    }
                    $entity->set('value', $values);
                }
                break;
            case 'text':
            case 'wysiwyg':
                $entity->set('value', $entity->get('textValue'));
                break;
            case 'bool':
                $entity->set('value', !empty($entity->get('boolValue')));
                break;
            case 'currency':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueCurrency', $entity->get('varcharValue'));
                break;
            case 'unit':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueUnit', $entity->get('varcharValue'));
                break;
            case 'int':
                $entity->set('value', $entity->get('intValue'));
                break;
            case 'float':
                $entity->set('value', $entity->get('floatValue'));
                break;
            case 'date':
                $entity->set('value', $entity->get('dateValue'));
                break;
            case 'datetime':
                $entity->set('value', $entity->get('datetimeValue'));
                break;
            case 'asset':
                $entity->set('value', $entity->get('varcharValue'));
                $entity->set('valueId', $entity->get('varcharValue'));
                if (!empty($entity->get('valueId'))) {
                    if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get('valueId')))) {
                        $entity->set('valueName', $attachment->get('name'));
                        $entity->set('valuePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment));
                    }
                }
                break;
            case 'enum':
                $entity->set('value', null);
                $entity->set('valueOptionId', $entity->get('varcharValue'));

                if ($entity->get('valueOptionId') !== null) {
                    $key = array_search($entity->get('varcharValue'), $entity->get('typeValueIds'));
                    if ($key !== false) {
                        $entity->set('value', $entity->get('typeValue')[$key]);
                    }
                }
                break;
            default:
                $entity->set('value', $entity->get('varcharValue'));
                break;
        }
    }

    public function getChannelLanguages(string $channelId): array
    {
        if (empty($channelId)) {
            return [];
        }

        if (!isset($this->channelLanguages[$channelId])) {
            $this->channelLanguages[$channelId] = [];
            if (!empty($channel = $this->getEntityManager()->getRepository('Channel')->get($channelId))) {
                $this->channelLanguages[$channelId] = $channel->get('locales');
            }
        }

        return $this->channelLanguages[$channelId];
    }

    public function clearRecord(string $id): void
    {
        $this->getPDO()->exec(
            "UPDATE `product_attribute_value` SET varchar_value=NULL, text_value=NULL, bool_value=0, float_value=NULL, int_value=NULL, date_value=NULL, datetime_value=NULL, owner_user_id=NULL, assigned_user_id=NULL WHERE id='$id'"
        );
    }

    public function get($id = null)
    {
        $entity = parent::get($id);
        $this->convertValue($entity);

        return $entity;
    }

    public function loadAttributes(array $ids): void
    {
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => $ids])->find() as $attribute) {
            $this->pavsAttributes[$attribute->get('id')] = $attribute;
        }
    }

    public function find(array $params = [])
    {
        $collection = parent::find($params);

        $this->loadAttributes(array_column($collection->toArray(), 'attributeId'));

        foreach ($collection as $entity) {
            $this->convertValue($entity);
        }

        return $collection;
    }

    public function findOne(array $params = [])
    {
        $entity = parent::findOne($params);
        if (!empty($entity)) {
            $this->convertValue($entity);
        }

        return $entity;
    }

    public function save(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = parent::save($entity, $options);
            if ($result) {
                $this->updateIsRequiredForLanguages($entity);
            }

            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }

            // if duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));
                $attributeName = !empty($attribute) ? $attribute->get('name') : $entity->get('attributeId');

                $channelName = $entity->get('scope');
                if ($channelName === 'Channel') {
                    $channel = $this->getEntityManager()->getRepository('Channel')->get($entity->get('channelId'));
                    $channelName = !empty($channel) ? $channel->get('name') : $entity->get('channelId');
                }

                throw new ProductAttributeAlreadyExists(
                    sprintf($this->getInjection('language')->translate('attributeRecordAlreadyExists', 'exceptions'), $attributeName, $channelName)
                );
            }

            throw $e;
        }

        return $result;
    }

    public function removeByProductId(string $productId): void
    {
        $productId = $this->getPDO()->quote($productId);

        $this->getPDO()->exec("DELETE FROM `product_attribute_value` WHERE product_id=$productId");
    }

    public function remove(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        $this->beforeRemove($entity, $options);

        try {
            $this->deleteFromDb($entity->get('id'));
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
            return false;
        }

        $this->afterRemove($entity, $options);

        return true;
    }

    public function findCopy(Entity $entity): ?Entity
    {
        $where = [
            'id!='        => $entity->get('id'),
            'language'    => $entity->get('language'),
            'productId'   => $entity->get('productId'),
            'attributeId' => $entity->get('attributeId'),
            'scope'       => $entity->get('scope'),
        ];
        if ($entity->get('scope') == 'Channel') {
            $where['channelId'] = $entity->get('channelId');
        }

        return $this->where($where)->findOne();
    }

    protected function getAttributeOptions(Entity $attribute, string $language): ?array
    {
        if ($language !== 'main') {
            $language = ucfirst(Util::toCamelCase(strtolower($language)));
        } else {
            $language = '';
        }

        $result = $attribute->get("typeValue$language");
        if (empty($result)) {
            $result = empty($attribute->get('typeValue')) ? [] : $attribute->get('typeValue');
        }
        if ($attribute->get('type') === 'enum' && !$attribute->get('prohibitedEmptyValue')) {
            array_unshift($result, '');
        }

        return $result;
    }

    protected function getAttributeOptionsIds(Entity $attribute): ?array
    {
        $result = !empty($attribute->get('typeValueIds')) ? $attribute->get('typeValueIds') : [];
        if ($attribute->get('type') === 'enum' && !$attribute->get('prohibitedEmptyValue')) {
            array_unshift($result, '');
        }

        return $result;
    }

    protected function populateDefault(Entity $entity, Entity $attribute): void
    {
        $entity->set('attributeType', $attribute->get('type'));

        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        if (empty($entity->get('language'))) {
            $entity->set('language', 'main');
        }

        if ($attribute->get('type') === 'enum' && !$entity->has('varcharValue') && !empty($attribute->get('enumDefault'))) {
            $entity->set('varcharValue', $attribute->get('enumDefault'));
        }

        if ($attribute->get('type') === 'unit') {
            if (!$entity->has('floatValue')) {
                $entity->set('floatValue', $attribute->get('unitDefault'));
            }

            if (!$entity->has('varcharValue')) {
                $entity->set('varcharValue', $attribute->get('unitDefaultUnit'));
            }
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        // for unique index
        if ($entity->get('channelId') === null) {
            $entity->set('channelId', '');
        }

        if (!$entity->isNew()) {
            self::$beforeSaveData = $this->getEntityManager()->getEntity('ProductAttributeValue', $entity->get('id'))->toArray();
        }

        $attribute = $this->getPavAttribute($entity);

        if ($entity->isNew()) {
            $this->populateDefault($entity, $attribute);
        }

        if ($entity->isNew() && !$this->getMetadata()->isModuleInstalled('OwnershipInheritance')) {
            $product = $entity->get('product');

            if (empty($entity->get('assignedUserId'))) {
                $entity->set('assignedUserId', $product->get('assignedUserId'));
            }

            if (empty($entity->get('ownerUserId'))) {
                $entity->set('ownerUserId', $product->get('ownerUserId'));
            }

            if (empty($entity->get('teamsIds'))) {
                $entity->set('teamsIds', array_column($product->get('teams')->toArray(), 'id'));
            }
        }

        /**
         * Check if UNIQUE enabled
         */
        if (!$entity->isNew() && $attribute->get('unique') && $entity->isAttributeChanged('value')) {
            $where = [
                'id!='            => $entity->id,
                'language'        => $entity->get('language'),
                'attributeId'     => $entity->get('attributeId'),
                'scope'           => $entity->get('scope'),
                'product.deleted' => false
            ];

            if ($entity->get('scope') === 'Channel') {
                $where['channelId'] = $entity->get('channelId');
            }

            switch ($entity->get('attributeType')) {
                case 'array':
                case 'multiEnum':
                    $where['textValue'] = @json_encode($entity->get('textValue'));
                    break;
                case 'text':
                case 'wysiwyg':
                    $where['textValue'] = $entity->get('textValue');
                    break;
                case 'bool':
                    $where['boolValue'] = $entity->get('boolValue');
                    break;
                case 'currency':
                case 'unit':
                    $where['floatValue'] = $entity->get('floatValue');
                    $where['varcharValue'] = $entity->get('varcharValue');
                    break;
                case 'int':
                    $where['intValue'] = $entity->get('intValue');
                    break;
                case 'float':
                    $where['floatValue'] = $entity->get('floatValue');
                    break;
                case 'date':
                    $where['dateValue'] = $entity->get('dateValue');
                    break;
                case 'datetime':
                    $where['datetimeValue'] = $entity->get('datetimeValue');
                    break;
                default:
                    $where['varcharValue'] = $entity->get('varcharValue');
                    break;
            }

            if (!empty($this->select(['id'])->join(['product'])->where($where)->findOne())) {
                throw new BadRequest(sprintf($this->exception("attributeShouldHaveBeUnique"), $entity->get('attribute')->get('name')));
            }
        }

        // create note
        if (!$entity->isNew() && $entity->isAttributeChanged('value')) {
            $this->createNote($entity);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'assignedUser', 'isInheritAssignedUser'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('assignedUserAttributeOwnership', null));
        }

        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'ownerUser', 'isInheritOwnerUser'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('ownerUserAttributeOwnership', null));
        }

        if (!$entity->isNew() && !empty($field = $this->getPreparedInheritedField($entity, 'teams', 'isInheritTeams'))) {
            $this->inheritOwnership($entity, $field, $this->getConfig()->get('teamsAttributeOwnership', null));
        }

        // update modifiedAt for product
        $this
            ->getPDO()
            ->exec("UPDATE `product` SET modified_at='{$entity->get('modifiedAt')}' WHERE id='{$entity->get('productId')}'");

        $this->moveImageFromTmp($entity);

        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param string $param
     *
     * @return string|null
     */
    protected function getPreparedInheritedField(Entity $entity, string $field, string $param): ?string
    {
        if ($entity->isAttributeChanged($param) && $entity->get($param)) {
            return $field;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function getInheritedEntity(Entity $entity, string $config): ?Entity
    {
        $result = null;

        if ($config == 'fromAttribute') {
            $result = $entity->get('attribute');
        } elseif ($config == 'fromProduct') {
            $result = $entity->get('product');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        return empty($this->findCopy($entity));
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ProductAttributeValue');
    }

    protected function createOwnNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function createAssignmentNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function moveImageFromTmp(Entity $attributeValue): void
    {
        if (!empty($attribute = $attributeValue->get('attribute')) && $attribute->get('type') === 'image' && !empty($attributeValue->get('value'))) {
            $file = $this->getEntityManager()->getEntity('Attachment', $attributeValue->get('value'));
            $this->getInjection('serviceFactory')->create($file->getEntityType())->moveFromTmp($file);
            $this->getEntityManager()->saveEntity($file);
        }
    }

    protected function createNote(Entity $entity): void
    {
        if (empty($data = $this->getNoteData($entity))) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set('type', 'Update');
        $note->set('parentId', $entity->get('productId'));
        $note->set('parentType', 'Product');
        $note->set('data', $data);
        $note->set('attributeId', $entity->get('id'));

        $this->getEntityManager()->saveEntity($note);
    }

    protected function getNoteData(Entity $entity): array
    {
        $fieldName = $this->getInjection('language')->translate('Attribute', 'custom', 'ProductAttributeValue') . ' ' . $entity->get('attributeName');

        $result = [
            'locale' => $entity->get('language') !== 'main' ? $entity->get('language') : '',
            'fields' => [$fieldName]
        ];

        $result['attributes']['was'][$fieldName] = self::$beforeSaveData['value'];
        $result['attributes']['became'][$fieldName] = in_array($entity->get('attribute')->get('type'), ['array', 'multiEnum']) ? json_decode($entity->get('value'), true)
            : $entity->get('value');

        if ($result['attributes']['was'][$fieldName] === null && ($result['attributes']['became'][$fieldName] === null || $result['attributes']['became'][$fieldName] === '')) {
            return [];
        }

        if ($entity->get('attributeType') === 'unit') {
            $result['attributes']['was'][$fieldName . 'Unit'] = self::$beforeSaveData['varcharValue'];
            $result['attributes']['became'][$fieldName . 'Unit'] = $entity->get('varcharValue');
        }

        if ($entity->get('attributeType') === 'currency') {
            $result['attributes']['was'][$fieldName . 'Currency'] = self::$beforeSaveData['varcharValue'];
            $result['attributes']['became'][$fieldName . 'Currency'] = $entity->get('varcharValue');
        }

        return $result;
    }

    protected function validateFieldsByType(Entity $entity): void
    {
        parent::validateFieldsByType($entity);

        $this->validateEnumAttribute($entity);
        $this->validateUnitAttribute($entity);
    }


    protected function validateEnumAttribute(Entity $entity): void
    {
        if (empty($attribute = $entity->get('attribute'))) {
            return;
        }

        $type = $attribute->get('type');

        if (!in_array($type, ['enum', 'multiEnum'])) {
            return;
        }

        if ($entity->isAttributeChanged('value') && !empty($entity->get('value'))) {
            $fieldOptions = $this->getAttributeOptionsIds($attribute);

            if (empty($fieldOptions) && $type === 'multiEnum') {
                $entity->set('value', null);
                $entity->set('textValue', null);
                return;
            }

            $value = $entity->get('value');

            if ($type == 'enum') {
                $value = [$value];
            }

            if ($type == 'multiEnum' && is_string($value)) {
                $value = @json_decode($value, true);
            }

            $errorMessage = sprintf($this->getInjection('language')->translate('noSuchAttributeOptions', 'exceptions', 'ProductAttributeValue'), $attribute->get('name'));

            if (!is_array($value)) {
                throw new BadRequest($errorMessage);
            }

            foreach ($value as $v) {
                if (!in_array($v, $fieldOptions)) {
                    throw new BadRequest($errorMessage);
                }
            }
        }
    }

    protected function validateUnitAttribute(Entity $entity): void
    {
        if (empty($attribute = $entity->get('attribute'))) {
            return;
        }

        $type = $attribute->get('type');

        if ($type !== 'unit') {
            return;
        }

        $language = $this->getInjection('container')->get('language');

        $unitsOfMeasure = $this->getConfig()->get('unitsOfMeasure');
        $unitsOfMeasure = empty($unitsOfMeasure) ? [] : Json::decode(Json::encode($unitsOfMeasure), true);

        $value = $entity->get('value');
        $unit = $entity->get('valueUnit');

        $label = $attribute->get('name');

        if ($value !== null && $value !== '' && empty($unit)) {
            throw new BadRequest(sprintf($language->translate('attributeUnitValueIsRequired', 'exceptions', 'ProductAttributeValue'), $label));
        }

        $measure = empty($attribute->get('typeValue') || !is_array($attribute->get('typeValue'))) ? '' : $attribute->get('typeValue')[0];

        if (!empty($unit)) {
            $units = empty($unitsOfMeasure[$measure]['unitList']) ? [] : $unitsOfMeasure[$measure]['unitList'];
            if (!in_array($unit, $units)) {
                throw new BadRequest(sprintf($language->translate('noSuchAttributeUnit', 'exceptions', 'ProductAttributeValue'), $label));
            }
        }
    }

    protected function updateIsRequiredForLanguages(Entity $entity): void
    {

    }
}
