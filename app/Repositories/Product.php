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

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;

/**
 * Class Product
 */
class Product extends AbstractRepository
{
    /**
     * @var string
     */
    protected $ownership = 'fromProduct';

    /**
     * @var string
     */
    protected $ownershipRelation = 'ProductAttributeValue';

    /**
     * @var string
     */
    protected $assignedUserOwnership = 'assignedUserAttributeOwnership';

    /**
     * @var string
     */
    protected $ownerUserOwnership = 'ownerUserAttributeOwnership';

    /**
     * @var string
     */
    protected $teamsOwnership = 'teamsAttributeOwnership';

    public function getProductsIdsViaAccountId(string $accountId): array
    {
        $accountId = $this->getPDO()->quote($accountId);
        $query = "SELECT DISTINCT p.id 
                  FROM `product_channel` pc 
                  JOIN `product` p ON pc.product_id=p.id AND p.deleted=0 
                  JOIN `channel` c ON pc.channel_id=c.id AND c.deleted=0
                  JOIN `account` a ON a.channel_id=c.id AND a.deleted=0
                  WHERE pc.deleted=0 AND a.id=$accountId";

        return $this
            ->getPDO()
            ->query($query)
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getCategoriesChannelsIds(string $productId): array
    {
        $productId = $this->getPDO()->quote($productId);

        $query = "SELECT channel_id 
                  FROM `category_channel` 
                  WHERE deleted=0 
                    AND category_id IN (SELECT category_id FROM `product_category` WHERE deleted=0 AND product_id=$productId)";

        return $this
            ->getPDO()
            ->query($query)
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return array
     */
    public function getInputLanguageList(): array
    {
        return $this->getConfig()->get('inputLanguageList', []);
    }

    public function unlinkProductsFromNonLeafCategories(): void
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT product_id as productId, category_id as categoryId FROM product_category WHERE category_id IN (SELECT DISTINCT category_parent_id FROM category WHERE category_parent_id IS NOT NULL AND deleted=0) AND deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row) {
            $product = $this->get($row['productId']);
            $category = $this->getEntityManager()->getRepository('Category')->get($row['categoryId']);
            if (!empty($product) && !empty($category)) {
                $this->unrelate($product, 'categories', $category);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        // prepare params
        $params = $this
            ->dispatch('ProductRepository', 'findRelated', new Event(['entity' => $entity, 'relationName' => $relationName, 'params' => $params]))
            ->getArgument('params');

        if ($relationName === 'productAttributeValues') {
            $params['limit'] = 9999;
        }

        if ($relationName == 'categories' && !empty($params)) {
            if (isset($params['additionalColumns']['sorting'])) {
                unset($params['additionalColumns']['sorting']);
            }
        }

        return parent::findRelated($entity, $relationName, $params);
    }

    /**
     * @inheritDoc
     */
    public function countRelated(Entity $entity, $relationName, array $params = [])
    {
        // prepare params
        $params = $this
            ->dispatch('ProductRepository', 'countRelated', new Event(['entity' => $entity, 'relationName' => $relationName, 'params' => $params]))
            ->getArgument('params');

        if ($relationName === 'productAttributeValues') {
            $params['limit'] = 9999;
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    /**
     * Is product can linked with non-lead category
     *
     * @param Entity|string $category
     *
     * @return bool
     * @throws BadRequest
     */
    public function isProductCanLinkToNonLeafCategory($category): bool
    {
        if ($this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            return true;
        }

        if (is_bool($category)) {
            throw new BadRequest($this->translate('massUnRelateBlocked', 'exceptions'));
        }

        if (is_string($category)) {
            $category = $this->getEntityManager()->getEntity('Category', $category);
        }

        if ($category->getChildren()->count() > 0) {
            throw new BadRequest($this->translate("productCanNotLinkToNonLeafCategory", 'exceptions', 'Product'));
        }

        return true;
    }

    /**
     * @param array $productsIds
     * @param array $categoriesIds
     *
     * @return array
     */
    public function getProductCategoryLinkData(array $productsIds, array $categoriesIds): array
    {
        $productsIds = implode("','", $productsIds);
        $categoriesIds = implode("','", $categoriesIds);

        return $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT * FROM product_category WHERE product_id IN ('$productsIds') AND category_id IN ('$categoriesIds') AND deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param string|Entity $productId
     * @param string|Entity $categoryId
     * @param int|null      $sorting
     * @param bool          $cascadeUpdate
     */
    public function updateProductCategorySortOrder($productId, $categoryId, int $sorting = null, bool $cascadeUpdate = true): void
    {
        if ($productId instanceof Entity) {
            $productId = $productId->get('id');
        }
        if ($categoryId instanceof Entity) {
            $categoryId = $categoryId->get('id');
        }

        // get link data
        $linkData = $this->getProductCategoryLinkData([$productId], [$categoryId]);

        // get max
        $max = (int)$linkData[0]['sorting'];

        if (is_null($sorting)) {
            $sorting = $max + 10;
            $cascadeUpdate = false;
        }

        // update current
        $this
            ->getEntityManager()
            ->nativeQuery("UPDATE product_category SET sorting='$sorting' WHERE category_id='$categoryId' AND product_id='$productId' AND deleted=0");

        if ($cascadeUpdate) {
            // get next records
            $ids = $this
                ->getEntityManager()
                ->nativeQuery("SELECT id FROM product_category WHERE sorting>='$sorting' AND category_id='$categoryId' AND deleted=0 AND product_id!='$productId' ORDER BY sorting")
                ->fetchAll(\PDO::FETCH_COLUMN);

            // update next records
            if (!empty($ids)) {
                // prepare sql
                $sql = '';
                foreach ($ids as $id) {
                    // increase max
                    $max = $max + 10;

                    // prepare sql
                    $sql .= "UPDATE product_category SET sorting='$max' WHERE id='$id';";
                }

                // execute sql
                $this->getEntityManager()->nativeQuery($sql);
            }
        }
    }

    /**
     * @param Entity|string $product
     * @param Entity|string $channel
     *
     * @return void
     */
    public function relatePfas($product, $channel): void
    {
        if (is_bool($product) || is_bool($channel)) {
            throw new BadRequest('Mass relate is unavailable.');
        }

        if (!$product instanceof Entity) {
            $product = $this->get($product);
        }

        $channelId = $channel instanceof Entity ? $channel->get('id') : $channel;

        $pfas = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->join('attribute')
            ->where([
                'attribute.id !=' => null,
                'productFamilyId' => $product->get('productFamilyId'),
                'channelId'       => $channelId
            ])
            ->find();

        foreach ($pfas as $pfa) {
            $pav = $this->getEntityManager()->getEntity('ProductAttributeValue');
            $pav->set('productId', $product->get('id'));
            $pav->set('attributeId', $pfa->get('attributeId'));
            $pav->set('isRequired', $pfa->get('isRequired'));
            $pav->set('scope', $pfa->get('scope'));
            $pav->set('channelId', $pfa->get('channelId'));

            try {
                $this->getEntityManager()->saveEntity($pav);
            } catch (ProductAttributeAlreadyExists $e) {
            }
        }
    }

    /**
     * @param Entity|string $product
     * @param Entity|string $channel
     *
     * @return void
     */
    public function unrelatePfas($product, $channel): void
    {
        if (is_bool($product) || is_bool($channel)) {
            throw new BadRequest('Mass unrelate is unavailable.');
        }

        $productId = $product instanceof Entity ? $product->get('id') : $product;
        $channelId = $channel instanceof Entity ? $channel->get('id') : $channel;

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $productId, 'channelId' => $channelId])
            ->find();

        foreach ($pavs as $pav) {
            $this->getEntityManager()->removeEntity($pav);
        }
    }

    public function isCategoryFromCatalogTrees(Entity $product, Entity $category): bool
    {
        if (!empty($catalog = $product->get('catalog'))) {
            $treesIds = array_column($catalog->get('categories')->toArray(), 'id');
        } else {
            $treesIds = $this->getEntityManager()->getRepository('Category')->getNotRelatedWithCatalogsTreeIds();
        }

        if (!in_array($category->getRoot()->get('id'), $treesIds)) {
            throw new BadRequest($this->translate("youShouldUseCategoriesFromThoseTreesThatLinkedWithProductCatalog", 'exceptions', 'Product'));
        }

        return true;
    }

    public function onCatalogCascadeChange(Entity $product, ?Entity $catalog): void
    {
        $categories = $product->get('categories');
        if (count($categories) == 0) {
            return;
        }

        foreach ($categories as $category) {
            $rootCatalogsIds = $category->getRoot()->getLinkMultipleIdList('catalogs');
            if (empty($catalog)) {
                if (!empty($rootCatalogsIds)) {
                    $this->unrelate($product, 'categories', $category);
                }
            } else {
                if (!in_array($catalog->get('id'), $rootCatalogsIds)) {
                    $this->unrelate($product, 'categories', $category);
                }
            }
        }
    }

    public function onCatalogRestrictChange(Entity $product, ?Entity $catalog): void
    {
        $categories = $product->get('categories');
        if (count($categories) == 0) {
            return;
        }

        foreach ($categories as $category) {
            $rootCatalogsIds = $category->getRoot()->getLinkMultipleIdList('catalogs');
            if (empty($catalog)) {
                if (!empty($rootCatalogsIds)) {
                    throw new BadRequest($this->translate("productCatalogChangeException", 'exceptions', 'Product'));
                }
            } else {
                if (!in_array($catalog->get('id'), $rootCatalogsIds)) {
                    throw new BadRequest($this->translate("productCatalogChangeException", 'exceptions', 'Product'));
                }
            }
        }
    }

    /**
     * @param string $categoryId
     * @param array  $ids
     *
     * @return void
     */
    public function updateSortOrderInCategory(string $categoryId, array $ids): void
    {
        $categoryId = $this->getPDO()->quote($categoryId);

        foreach ($ids as $k => $id) {
            $id = $this->getPDO()->quote($id);
            $sortOrder = (int)$k * 10;
            $this->getPDO()->exec("UPDATE `product_category` SET sorting=$sortOrder WHERE product_id=$id AND category_id=$categoryId AND deleted=0");
        }
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
        $this->addDependency('queueManager');
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isSkippedValidation('isProductSkuUnique') && !$this->isFieldUnique($entity, 'sku')) {
            throw new BadRequest(sprintf($this->translate('productWithSuchSkuAlreadyExist', 'exceptions', 'Product'), $entity->get('sku')));
        }

        if (!$entity->isSkippedValidation('isProductEanUnique') && !$this->isFieldUnique($entity, 'ean')) {
            throw new BadRequest(sprintf($this->translate('eanShouldHaveUniqueValue', 'exceptions', 'Product'), $entity->get('ean')));
        }

        if (!$entity->isSkippedValidation('isProductMpnUnique') && !$this->isFieldUnique($entity, 'mpn')) {
            throw new BadRequest(sprintf($this->translate('mpnShouldHaveUniqueValue', 'exceptions', 'Product'), $entity->get('mpn')));
        }

        if ($entity->isAttributeChanged('catalogId')) {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->{"onCatalog{$mode}Change"}($entity, $entity->get('catalog'));
        }

        if ($entity->isAttributeChanged('productFamilyId')) {
            $this->onProductFamilyChange($entity);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->translate("youCantChangeFieldOfTypeInProduct", 'exceptions', 'Product'));
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws Error
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        // save attributes
        $this->saveAttributes($entity);

        if ($entity->isAttributeChanged('productFamilyId')) {
            if (empty($entity->skipUpdateProductAttributesByProductFamily) && empty($entity->isDuplicate)) {
                $this->updateProductAttributesByProductFamily($entity);
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('isInheritAssignedUser') && $entity->get('isInheritAssignedUser')) {
            $this->inheritOwnership($entity, 'assignedUser', $this->getConfig()->get('assignedUserProductOwnership', null));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('isInheritOwnerUser') && $entity->get('isInheritOwnerUser')) {
            $this->inheritOwnership($entity, 'ownerUser', $this->getConfig()->get('ownerUserProductOwnership', null));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('isInheritTeams') && $entity->get('isInheritTeams')) {
            $this->inheritOwnership($entity, 'teams', $this->getConfig()->get('teamsProductOwnership', null));
        }

        // parent action
        parent::afterSave($entity, $options);

        $this->setInheritedOwnership($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('ProductAttributeValue')->removeByProductId($entity->get('id'));
        $this->getEntityManager()->getRepository('AssociatedProduct')->removeByProductId($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    public function relateCategories(Entity $product, $category, $data, $options)
    {
        if (is_bool($category)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        if (is_string($category)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($category);
        }

        $this->isCategoryFromCatalogTrees($product, $category);
        $this->isProductCanLinkToNonLeafCategory($category);

        $result = $this->getMapper()->addRelation($product, 'categories', $category->get('id'));
        $this->updateProductCategorySortOrder($product, $category);
        $this->getEntityManager()->getRepository('ProductChannel')->createRelationshipViaCategory($product, $category);

        return $result;
    }

    public function unrelateCategories(Entity $product, $category, $options)
    {
        if (is_bool($category)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        if (is_string($category)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($category);
        }

        $result = $this->getMapper()->removeRelation($product, 'categories', $category->get('id'));
        $this->getEntityManager()->getRepository('ProductChannel')->deleteRelationshipViaCategory($product, $category);

        return $result;
    }

    public function getProductsHierarchyMap(array $productIds): array
    {
        $query = "SELECT entity_id AS childId, parent_id AS parentId
                FROM product_hierarchy
                WHERE entity_id IN ('" . implode("','", $productIds) . "') and deleted = 0";

        $result = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

        return empty($result) ? [] : $result;
    }

    protected function onProductFamilyChange(Entity $product): void
    {
        if (empty($product->getFetched('productFamilyId'))) {
            return;
        }

        $mode = $this->getConfig()->get('behaviorOnProductFamilyChange', 'retainAllInheritedAttributes');

        if ($mode == 'retainAllInheritedAttributes') {
            return;
        }

        $where = [
            'productFamilyId' => $product->getFetched('productFamilyId')
        ];

        if (!empty($product->get('productFamilyId'))) {
            $where['attributeId!='] = array_column($product->get('productFamily')->get('productFamilyAttributes')->toArray(), 'attributeId');
        }

        $pfas = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->where($where)
            ->find();

        if (count($pfas) === 0) {
            return;
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $product->get('id'), 'attributeId' => array_column($pfas->toArray(), 'attributeId')])
            ->find();

        if (count($pavs) === 0) {
            return;
        }

        foreach ($pfas as $pfa) {
            foreach ($pavs as $pav) {
                if ($pav->get('attributeId') === $pfa->get('attributeId') && $pav->get('scope') === $pfa->get('scope') && $pav->get('isRequired') === $pfa->get('isRequired')) {
                    if ($pfa->get('scope') === 'Channel' && $pav->get('channelId') !== $pfa->get('channelId')) {
                        continue 1;
                    }
                    if ($mode === 'removeOnlyInheritedAttributesWithNoValue') {
                        if ($pav->get('value') !== null && $pav->get('value') !== '') {
                            continue 1;
                        }
                    }

                    $this->getEntityManager()->removeEntity($pav);
                }
            }
        }
    }

    /**
     * @param Entity $entity
     * @param string $field
     *
     * @return bool
     */
    protected function isFieldUnique(Entity $entity, string $field): bool
    {
        $result = true;

        if ($entity->hasField($field) && !empty($entity->get($field))) {
            $products = $this
                ->getEntityManager()
                ->getRepository('Product')
                ->where(
                    [
                        $field      => $entity->get($field),
                        'catalogId' => $entity->get('catalogId'),
                        'id!='      => $entity->id
                    ]
                )
                ->count();

            if ($products > 0) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getInheritedEntity(Entity $entity, string $config): ?Entity
    {
        $result = null;

        if ($config == 'fromCatalog') {
            $result = $entity->get('catalog');
        } elseif ($config == 'fromProductFamily') {
            $result = $entity->get('productFamily');
        }

        return $result;
    }

    protected function updateProductAttributesByProductFamily(Entity $product): bool
    {
        if (empty($product->get('productFamilyId'))) {
            return true;
        }

        $pfas = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->where(['productFamilyId' => $product->get('productFamilyId')])
            ->find();

        foreach ($pfas as $pfa) {
            $productAttributeValue = $this->getEntityManager()->getRepository('ProductAttributeValue')->get();
            $productAttributeValue->set(
                [
                    'productId'   => $product->get('id'),
                    'attributeId' => $pfa->get('attributeId'),
                    'isRequired'  => $pfa->get('isRequired'),
                    'scope'       => $pfa->get('scope'),
                    'channelId'   => $pfa->get('channelId')
                ]
            );

            if (!$this->getMetadata()->isModuleInstalled('OwnershipInheritance')) {
                $productAttributeValue->set(
                    [
                        'assignedUserId' => $product->get('assignedUserId'),
                        'ownerUserId'    => $product->get('ownerUserId'),
                        'teamsIds'       => $product->get('teamsIds')
                    ]
                );
            }

            $productAttributeValue->skipVariantValidation = true;
            $productAttributeValue->skipProductChannelValidation = true;
            $productAttributeValue->clearCompletenessFields = true;

            try {
                $this->getEntityManager()->saveEntity($productAttributeValue);
            } catch (BadRequest $e) {
                // ignore
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     * @throws Error
     */
    protected function saveAttributes(Entity $product): bool
    {
        if (!empty($product->productAttribute)) {
            $data = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->where(
                    [
                        'productId'   => $product->get('id'),
                        'attributeId' => array_keys($product->productAttribute),
                        'scope'       => 'Global'
                    ]
                )
                ->find();

            // prepare exists
            $exists = [];
            if (count($data) > 0) {
                foreach ($data as $v) {
                    $exists[$v->get('attributeId')] = $v;
                }
            }

            foreach ($product->productAttribute as $attributeId => $values) {
                if (isset($exists[$attributeId])) {
                    $entity = $exists[$attributeId];
                } else {
                    $entity = $this->getEntityManager()->getEntity('ProductAttributeValue');
                    $entity->set('productId', $product->get('id'));
                    $entity->set('attributeId', $attributeId);
                    $entity->set('scope', 'Global');
                }

                foreach ($values['locales'] as $locale => $value) {
                    if ($locale == 'default') {
                        $entity->set('value', $value);
                    } else {
                        // prepare locale
                        $locale = Util::toCamelCase(strtolower($locale), '_', true);
                        $entity->set("value$locale", $value);
                    }
                }

                if (isset($values['data']) && !empty($values['data'])) {
                    foreach ($values['data'] as $field => $item) {
                        $entity->set($field, $item);
                    }
                }

                $this->getEntityManager()->saveEntity($entity);
            }
        }

        return true;
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, $scope = ''): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function dispatch(string $target, string $action, Event $event): Event
    {
        return $this->getInjection('eventManager')->dispatch($target, $action, $event);
    }
}
