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
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;

class ProductAttributeValue extends AbstractRepository
{
    protected static $beforeSaveData = [];

    protected array $channelLanguages = [];
    protected array $products = [];
    protected array $classificationAttributes = [];
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

    public function getPavAttribute(Entity $entity): \Pim\Entities\Attribute
    {
        if (empty($this->pavsAttributes[$entity->get('attributeId')])) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            if (empty($attribute)) {
                $this->getEntityManager()->getRepository('ClassificationAttribute')->where(['attributeId' => $entity->get('attributeId')])->removeCollection();
                $this->where(['attributeId' => $entity->get('attributeId')])->removeCollection();
                throw new BadRequest("Attribute '{$entity->get('attributeId')}' does not exist.");
            }
            $this->pavsAttributes[$entity->get('attributeId')] = $attribute;
        }

        return $this->pavsAttributes[$entity->get('attributeId')];
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
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
                  FROM product_attribute_value
                  WHERE deleted=0
                    AND product_id IN ('" . implode("','", array_column($products, 'id')) . "')
                    AND attribute_id='{$pav->get('attributeId')}'
                    AND product_attribute_value.language='{$pav->get('language')}'
                    AND scope='{$pav->get('scope')}'
                    AND is_variant_specific_attribute='{$pav->get('isVariantSpecificAttribute')}'";

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

    public function getChildPavForProduct(Entity $parentPav, Entity $childProduct): ?Entity
    {
        $where = [
            'productId'                  => $childProduct->get('id'),
            'attributeId'                => $parentPav->get('attributeId'),
            'language'                   => $parentPav->get('language'),
            'scope'                      => $parentPav->get('scope'),
            'isVariantSpecificAttribute' => $parentPav->get('isVariantSpecificAttribute'),
        ];

        if ($parentPav->get('scope') === 'Channel') {
            $where['channelId'] = $parentPav->get('channelId');
        }

        return $this->where($where)->findOne();
    }

    public function isPavRelationInherited(Entity $entity): bool
    {
        return !empty($this->getParentPav($entity));
    }

    public function isPavValueInherited(Entity $entity): ?bool
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
                && $pav->get('isVariantSpecificAttribute') === $entity->get('isVariantSpecificAttribute')
                && $this->arePavsValuesEqual($pav, $entity)
            ) {
                return true;
            }
        }

        return false;
    }

    public function arePavsValuesEqual(Entity $pav1, Entity $pav2): bool
    {
        switch ($pav1->get('attributeType')) {
            case 'array':
            case 'extensibleMultiEnum':
            case 'text':
            case 'wysiwyg':
                $result = Entity::areValuesEqual(Entity::TEXT, $pav1->get('textValue'), $pav2->get('textValue'));
                break;
            case 'bool':
                $result = Entity::areValuesEqual(Entity::BOOL, $pav1->get('boolValue'), $pav2->get('boolValue'));
                break;
            case 'currency':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
                break;
            case 'int':
                $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue'), $pav2->get('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
                break;
            case 'rangeInt':
                $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue'), $pav2->get('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue1'), $pav2->get('intValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
                break;
            case 'float':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
                break;
            case 'rangeFloat':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue1'), $pav2->get('floatValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                }
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

    public function findClassificationAttribute(Entity $pav): ?Entity
    {
        $product = $this->getProductById((string)$pav->get('productId'));
        if (empty($product)) {
            return null;
        }

        $classifications = $product->get('classifications');
        if (empty($classifications[0])) {
            return null;
        }

        foreach ($classifications as $classification) {
            $classificationAttributes = $this->getClassificationAttributesByClassificationId($classification->get('id'));
            foreach ($classificationAttributes as $pfa) {
                if ($pfa->get('attributeId') !== $pav->get('attributeId')) {
                    continue;
                }

                if ($pfa->get('scope') !== $pav->get('scope')) {
                    continue;
                }

                if ($pfa->get('language') !== $pav->get('language')) {
                    continue;
                }

                if ($pav->get('scope') === 'Channel' && $pfa->get('channelId') !== $pav->get('channelId')) {
                    continue;
                }

                return $pfa;
            }
        }

        return null;
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
            "UPDATE `product_attribute_value` SET varchar_value=NULL, text_value=NULL, bool_value=0, float_value=NULL, int_value=NULL, date_value=NULL, datetime_value=NULL WHERE id='$id'"
        );
    }

    public function loadAttributes(array $ids): void
    {
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => $ids])->find() as $attribute) {
            $this->pavsAttributes[$attribute->get('id')] = $attribute;
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = parent::save($entity, $options);
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
        $this
            ->where(['productId' => $productId])
            ->removeCollection();
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

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        $where = [
            'id!='        => $entity->get('id'),
            'language'    => $entity->get('language'),
            'productId'   => $entity->get('productId'),
            'attributeId' => $entity->get('attributeId'),
            'scope'       => $entity->get('scope'),
            'deleted'     => $deleted,
        ];
        if ($entity->get('scope') == 'Channel') {
            $where['channelId'] = $entity->get('channelId');
        }
        // Do not use find method from this class, it can cause infinite loop
        $this->limit(0, 1)->where($where);
        $collection = parent::find(['withDeleted' => $deleted]);
        return count($collection) > 0 ? $collection[0] : null;
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

        if ($entity->isNew()) {
            if ($attribute->get('type') === 'extensibleEnum' && empty($entity->get('varcharValue')) && !empty($attribute->get('enumDefault'))) {
                $entity->set('varcharValue', $attribute->get('enumDefault'));
            }

            if (!empty($attribute->get('measureId')) && empty($entity->get('varcharValue')) && !empty($attribute->get('defaultUnit'))) {
                $entity->set('varcharValue', $attribute->get('defaultUnit'));
            }
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('productId'))) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions', 'Global'), 'Product'));
        }

        if (empty($entity->get('attributeId'))) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions', 'Global'), 'Attribute'));
        }

        // for unique index
        if ($entity->get('channelId') === null) {
            $entity->set('channelId', '');
        }

        if (!$entity->isNew()) {
            self::$beforeSaveData = $this->getEntityManager()->getEntity('ProductAttributeValue', $entity->get('id'))->toArray();
        }

        $attribute = $this->getPavAttribute($entity);
        if (!empty($attribute)) {
            $this->validateValue($attribute, $entity);
            $this->populateDefault($entity, $attribute);
        }

        if ($entity->isNew() && !$this->getMetadata()->isModuleInstalled('OwnershipInheritance')) {
            $product = $this->getEntityManager()->getRepository('Product')->get($entity->get('productId'));
            if (!empty($product)) {
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
        }

        $type = $attribute->get('type');

        /**
         * Text length validation
         */
        if (in_array($type, ['varchar', 'text', 'wysiwyg']) && $entity->get('value') !== null) {
            $countBytesInsteadOfCharacters = (bool)$entity->get('countBytesInsteadOfCharacters');
            $fieldValue = (string)$entity->get('value');
            $length = $countBytesInsteadOfCharacters ? strlen($fieldValue) : mb_strlen($fieldValue);
            $maxLength = (int)$entity->get('maxLength');
            if (!empty($maxLength) && $length > $maxLength) {
                throw new BadRequest(
                    sprintf($this->getInjection('language')->translate('maxLengthIsExceeded', 'exceptions', 'Global'), $attribute->get('name'), $maxLength, $length)
                );
            }
        }

        /**
         * Rounding float Values using amountOfDigitsAfterComma
         */
        $amountOfDigitsAfterComma = $entity->get('amountOfDigitsAfterComma');
        if ($amountOfDigitsAfterComma !== null) {
            switch ($type) {
                case 'float':
                case 'currency':
                    if ($entity->get('value') !== null) {
                        $entity->set('floatValue', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('value'), (int)$amountOfDigitsAfterComma));
                        $entity->set('value', $entity->get('floatValue'));
                    }
                    break;
                case 'rangeFloat':
                    if ($entity->get('floatValue') !== null) {
                        $entity->set('floatValue', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('floatValue'), (int)$amountOfDigitsAfterComma));
                        $entity->set('valueFrom', $entity->get('floatValue'));
                    }
                    if ($entity->get('floatValue1') !== null) {
                        $entity->set('floatValue1', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('floatValue1'), (int)$amountOfDigitsAfterComma));
                        $entity->set('valueTo', $entity->get('floatValue1'));
                    }
                    break;
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
                case 'extensibleMultiEnum':
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
                    $where['floatValue'] = $entity->get('floatValue');
                    $where['varcharValue'] = $entity->get('varcharValue');
                    break;
                case 'int':
                    $where['intValue'] = $entity->get('intValue');
                    $where['varcharValue'] = $entity->get('varcharValue');
                    break;
                case 'float':
                    $where['floatValue'] = $entity->get('floatValue');
                    $where['varcharValue'] = $entity->get('varcharValue');
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
        if (!$entity->isNew()) {
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

    protected function validateValue(Entity $attribute, Entity $pav): void
    {
        switch ($attribute->get('type')) {
            case 'extensibleEnum':
                $id = $pav->get('varcharValue');
                if (!empty($id)) {
                    $option = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                        ->select(['id'])
                        ->where([
                            'id'               => $id,
                            'extensibleEnumId' => $attribute->get('extensibleEnumId') ?? 'no-such-measure'
                        ])
                        ->findOne();
                    if (empty($option)) {
                        throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchOptions', 'exceptions'), $id, $attribute->get('name')));
                    }
                }
                break;
            case 'extensibleMultiEnum':
                $ids = @json_decode((string)$pav->get('textValue'), true);
                if (!empty($ids)) {
                    $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                        ->select(['id'])
                        ->where([
                            'id'               => $ids,
                            'extensibleEnumId' => $attribute->get('extensibleEnumId') ?? 'no-such-measure'
                        ])
                        ->find();
                    $diff = array_diff($ids, array_column($options->toArray(), 'id'));
                    foreach ($diff as $id) {
                        throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchOptions', 'exceptions'), $id, $attribute->get('name')));
                    }
                }
                break;
            case 'rangeInt':
                if ($pav->get('intValue1') !== null && $pav->get('intValue') !== null && $pav->get('intValue') > $pav->get('intValue1')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $fromLabel = $this->getLanguage()->translate('valueTo', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$attribute->get('name') . ' ' . $fromLabel, $pav->get('intValue')], $message));
                }
                break;
            case 'rangeFloat':
                if ($pav->get('floatValue1') !== null && $pav->get('floatValue') !== null && $pav->get('floatValue') > $pav->get('floatValue1')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $fromLabel = $this->getLanguage()->translate('valueTo', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$attribute->get('name') . ' ' . $fromLabel, $pav->get('floatValue')], $message));
                }
                break;
        }

        if (in_array($attribute->get('type'), ['rangeInt', 'rangeFloat', 'int', 'float']) && !empty($pav->get('varcharValue'))) {
            $unit = $this->getEntityManager()->getRepository('Unit')
                ->select(['id'])
                ->where([
                    'id'        => $pav->get('varcharValue'),
                    'measureId' => $attribute->get('measureId') ?? 'no-such-measure'
                ])
                ->findOne();

            if (empty($unit)) {
                throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchUnit', 'exceptions', 'Global'), $pav->get('varcharValue'), $attribute->get('name')));
            }
        }
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
        return empty($this->getDuplicateEntity($entity));
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
        if (
            !$entity->isAttributeChanged('value')
            && !$entity->isAttributeChanged('valueId')
            && !$entity->isAttributeChanged('valueFrom')
            && !$entity->isAttributeChanged('valueTo')
            && !$entity->isAttributeChanged('valueCurrency')
            && !$entity->isAttributeChanged('valueUnitId')
        ) {
            return;
        }

        $data = $this->getNoteData($entity);
        if (empty($data)) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set('type', 'Update');
        $note->set('parentId', $entity->get('productId'));
        $note->set('parentType', 'Product');
        $note->set('data', $data);
        $note->set('attributeId', $entity->get('attributeId'));

        $this->getEntityManager()->saveEntity($note);
    }

    protected function getNoteData(Entity $entity): array
    {
        $result = [
            'locale' => $entity->get('language') !== 'main' ? $entity->get('language') : '',
            'fields' => []
        ];

        switch ($entity->get('attributeType')) {
            case 'rangeInt':
            case 'rangeFloat':
                if ($entity->isAttributeChanged('valueFrom') && self::$beforeSaveData['valueFrom'] !== $entity->get('valueFrom')) {
                    $result['fields'][] = 'valueFrom';
                    $result['attributes']['was']['valueFrom'] = self::$beforeSaveData['valueFrom'];
                    $result['attributes']['became']['valueFrom'] = $entity->get('valueFrom');
                }

                if ($entity->isAttributeChanged('valueTo') && self::$beforeSaveData['valueTo'] !== $entity->get('valueTo')) {
                    $result['fields'][] = 'valueTo';
                    $result['attributes']['was']['valueTo'] = self::$beforeSaveData['valueTo'];
                    $result['attributes']['became']['valueTo'] = $entity->get('valueTo');
                }
                break;
            case 'array':
            case 'extensibleMultiEnum':
                $result['fields'][] = 'value';
                $result['attributes']['was']['value'] = self::$beforeSaveData['value'];
                $result['attributes']['became']['value'] = json_decode($entity->get('value'), true);
                break;
            case 'currency':
                if ($entity->isAttributeChanged('value') && self::$beforeSaveData['value'] !== $entity->get('value')) {
                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = self::$beforeSaveData['value'];
                    $result['attributes']['became']['value'] = $entity->get('value');
                }
                if ($entity->isAttributeChanged('valueCurrency') && self::$beforeSaveData['valueCurrency'] !== $entity->get('valueCurrency')) {
                    $result['fields'][] = 'valueCurrency';
                    $result['attributes']['was']['valueCurrency'] = self::$beforeSaveData['valueCurrency'];
                    $result['attributes']['became']['valueCurrency'] = $entity->get('valueCurrency');
                }
                break;
            case 'asset':
                $result['fields'][] = 'value';
                $result['attributes']['was']['valueId'] = self::$beforeSaveData['valueId'];
                $result['attributes']['became']['valueId'] = $entity->get('valueId');
                break;
            default:
                $result['fields'][] = 'value';
                $result['attributes']['was']['value'] = self::$beforeSaveData['value'];
                $result['attributes']['became']['value'] = $entity->get('value');
        }

        if ($entity->isAttributeChanged('valueUnitId') && self::$beforeSaveData['valueUnitId'] !== $entity->get('valueUnitId')) {
            $result['fields'][] = 'valueUnit';
            $result['attributes']['was']['valueUnitId'] = self::$beforeSaveData['valueUnitId'];
            $result['attributes']['became']['valueUnitId'] = $entity->get('valueUnitId');
        }

        if (empty($result['fields'])) {
            return [];
        }

        return $result;
    }

    public function getProductById(string $productId): ?Entity
    {
        if (empty($productId)) {
            return null;
        }

        if (!array_key_exists($productId, $this->products)) {
            $this->products[$productId] = $this->getEntityManager()->getRepository('Product')->get($productId);
        }

        return $this->products[$productId];
    }

    public function getClassificationAttributesByClassificationId(string $classificationId): EntityCollection
    {
        if (!array_key_exists($classificationId, $this->classificationAttributes)) {
            $this->classificationAttributes[$classificationId] = $this
                ->getEntityManager()
                ->getRepository('ClassificationAttribute')
                ->where(['classificationId' => $classificationId])
                ->find();
        }

        return $this->classificationAttributes[$classificationId];
    }
}
