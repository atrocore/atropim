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
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ChannelAlreadyRelatedToProduct;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Treo\Core\EventManager\Event;

/**
 * Class Product
 */
class Product extends Base
{
    /**
     * @return array
     */
    public function getInputLanguageList(): array
    {
        return $this->getConfig()->get('inputLanguageList', []);
    }

    /**
     * @param Entity $entity
     * @param array  $types
     *
     * @return array
     */
    public function findRelatedAssetsByTypes(Entity $entity, array $types): array
    {
        $id = $entity->get('id');
        $types = implode("','", $types);

        $sql = "SELECT a.*, r.channel
                FROM product_asset r 
                LEFT JOIN asset a ON a.id=r.asset_id 
                WHERE 
                      r.deleted=0 
                  AND a.deleted=0 
                  AND a.type IN ('$types') 
                  AND r.product_id='$id' 
                ORDER BY r.sorting ASC";

        $result = $this->getEntityManager()->getRepository('Asset')->findByQuery($sql)->toArray();

        return $this->prepareAssets($entity, $result);
    }

    /**
     * @param Entity $entity
     * @param array  $ids
     *
     * @return array
     */
    public function findRelatedAssetsByIds(Entity $entity, array $ids): array
    {
        $id = $entity->get('id');
        $ids = implode("','", $ids);

        $sql = "SELECT a.*, r.channel
                FROM product_asset r 
                LEFT JOIN asset a ON a.id=r.asset_id 
                WHERE 
                      r.deleted=0 
                  AND a.deleted=0 
                  AND a.id IN ('$ids')
                  AND r.product_id='$id' 
                ORDER BY r.sorting ASC";

        $result = $this->getEntityManager()->getRepository('Asset')->findByQuery($sql)->toArray();

        return $this->prepareAssets($entity, $result);
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
            $this->filterByChannel($entity, $params);
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
            $this->filterByChannel($entity, $params);
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    /**
     * @param Entity $entity
     * @param array  $params
     */
    protected function filterByChannel(Entity $entity, array &$params)
    {
        // prepare channels ids
        $channelsIds = array_column($entity->get('channels')->toArray(), 'id');
        $channelsIds = $this
            ->dispatch('ProductRepository', 'getChannelsForFilter', new Event(['entity' => $entity, 'params' => $params, 'channelsIds' => $channelsIds]))
            ->getArgument('channelsIds');
        $channelsIds[] = 'no-such-id';
        $channelsIds = implode("','", $channelsIds);

        $params['customWhere'] .= " AND (product_attribute_value.scope='Global' OR (product_attribute_value.scope='Channel' AND product_attribute_value.channel_id IN ('$channelsIds')))";
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

        if (is_string($category)) {
            /** @var \Pim\Entities\Category $category */
            $category = $this->getEntityManager()->getEntity('Category', $category);
        }

        if ($category->getChildren()->count() > 0) {
            throw new BadRequest($this->translate("productCanNotLinkToNonLeafCategory", 'exceptions', 'Product'));
        }

        return true;
    }

    /**
     * Link category(tree) channels to product
     *
     * @param Entity|string                 $product
     * @param \Pim\Entities\Category|string $category
     * @param bool
     *
     * @return bool
     * @throws Error
     */
    public function linkCategoryChannels($product, $category, bool $unRelate = false): bool
    {
        if (is_string($product)) {
            $product = $this->getEntityManager()->getEntity('Product', $product);
        }
        if (is_string($category)) {
            $category = $this->getEntityManager()->getEntity('Category', $category);
        }

        // get root
        $root = $category->getRoot();

        if ($unRelate) {
            $productCategories = $product->get('categories');
            if ($productCategories->count() > 0) {
                foreach ($productCategories as $productCategory) {
                    if ($productCategory->getRoot() == $root) {
                        return false;
                    }
                }
            }
        }

        // get channels
        $channels = $root->get('channels');
        if ($channels->count() > 0) {
            foreach ($channels as $channel) {
                if (!$unRelate) {
                    $product->fromCategoryTree = true;
                    try {
                        $this->relate($product, 'channels', $channel);
                    } catch (ChannelAlreadyRelatedToProduct $e) {
                        $this->updateChannelRelationData($product, $channel, null, true);
                    }
                } else {
                    $product->skipIsFromCategoryTreeValidation = true;
                    $this->unrelateForce($product, 'channels', $channel);
                }
            }
        }

        return true;
    }

    /**
     * @param string $productId
     *
     * @return array
     */
    public function getChannelRelationData(string $productId): array
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT channel_id as channelId, is_active AS isActive, from_category_tree as isFromCategoryTree FROM product_channel WHERE product_id='{$productId}' AND deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            $result[$row['channelId']] = $row;
        }

        return $result;
    }

    /**
     * @param string|Entity $productId
     * @param string|Entity $channelId
     * @param bool|null     $isActive
     * @param bool|null     $fromCategoryTree
     */
    public function updateChannelRelationData($productId, $channelId, bool $isActive = null, bool $fromCategoryTree = null)
    {
        if ($productId instanceof Entity) {
            $productId = $productId->get('id');
        }

        if ($channelId instanceof Entity) {
            $channelId = $channelId->get('id');
        }

        $data = [];
        if (!is_null($isActive)) {
            $data[] = 'is_active=' . (int)$isActive;
        }
        if (!is_null($fromCategoryTree)) {
            $data[] = 'from_category_tree=' . (int)$fromCategoryTree;
        }

        if (!empty($data)) {
            $this
                ->getEntityManager()
                ->nativeQuery("UPDATE product_channel SET " . implode(',', $data) . " WHERE product_id='$productId' AND channel_id='$channelId' AND deleted=0");
        }
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

        if (is_null($sorting)) {
            $sorting = time();
            $cascadeUpdate = false;
        }

        // get link data
        $linkData = $this->getProductCategoryLinkData([$productId], [$categoryId]);

        // get max
        $max = (int)$linkData[0]['sorting'];

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
     * Is category already related
     *
     * @param Entity $product
     * @param Entity $category
     *
     * @return bool
     * @throws BadRequest
     */
    public function isCategoryAlreadyRelated(Entity $product, Entity $category): bool
    {
        /** @var array $productCategoriesIds */
        $productCategoriesIds = array_column($product->get('categories')->toArray(), 'id');

        if (in_array($category->get('id'), $productCategoriesIds)) {
            throw new BadRequest($this->translate("isCategoryAlreadyRelated", 'exceptions', 'Product'));
        }

        return true;
    }

    /**
     * Is channel already related
     *
     * @param Entity|string $product
     * @param Entity|string $channel
     *
     * @return bool
     * @throws BadRequest
     */
    public function isChannelAlreadyRelated($product, $channel): bool
    {
        if (!$product instanceof Entity) {
            /** @var Entity $product */
            $product = $this->get($product);
        }

        if (!$channel instanceof Entity) {
            /** @var Entity $channel */
            $channel = $this->getEntityManager()->getRepository('Channel')->get($channel);
        }

        /** @var array $productChannelsIds */
        $productChannelsIds = array_column($product->get('channels')->toArray(), 'id');

        if (in_array($channel->get('id'), $productChannelsIds)) {
            throw new ChannelAlreadyRelatedToProduct($this->translate('isChannelAlreadyRelated', 'exceptions', 'Product'));
        }

        return true;
    }

    /**
     * @param Entity $product
     * @param Entity $category
     *
     * @return bool
     * @throws BadRequest
     */
    public function isCategoryFromCatalogTrees(Entity $product, Entity $category): bool
    {
        if (!empty($catalog = $product->get('catalog'))) {
            /** @var array $treesIds */
            $treesIds = array_column($catalog->get('categories')->toArray(), 'id');

            /** @var string $rootId */
            $rootId = $category->getRoot()->get('id');

            if (!in_array($rootId, $treesIds)) {
                throw new BadRequest($this->translate("You should use categories from those trees that linked with product catalog", 'exceptions', 'Product'));
            }
        }

        return true;
    }

    /**
     * @param Entity $product
     * @param Entity $catalog
     *
     * @return bool
     * @throws BadRequest
     */
    public function isProductCategoriesInSelectedCatalog(Entity $product, Entity $catalog): bool
    {
        /** @var array $catalogTreesIds */
        $catalogTreesIds = array_column($catalog->get('categories')->toArray(), 'id');

        /** @var EntityCollection $categories */
        $categories = $product->get('categories');
        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                if (!in_array($category->getRoot()->get('id'), $catalogTreesIds)) {
                    throw new BadRequest($this->translate("You should use categories from those trees that linked with product catalog", 'exceptions', 'Product'));
                }
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
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

        // update pavs by product family
        if ($entity->isAttributeChanged('productFamilyId')) {
            if (empty($entity->skipUpdateProductAttributesByProductFamily) && empty($entity->isDuplicate)) {
                $this->updateProductAttributesByProductFamily($entity);
            }
        }

        // parent action
        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $product
     *
     * @return bool
     */
    protected function updateProductAttributesByProductFamily(Entity $product): bool
    {
        // unlink attributes from old product family
        if (!$product->isNew() && !empty($pavs = $product->get('productAttributeValues')) && $pavs->count() > 0) {
            foreach ($pavs as $pav) {
                if (!empty($pav->get('productFamilyAttributeId'))) {
                    $pav->set('productFamilyAttributeId', null);
                    $this->getEntityManager()->saveEntity($pav);
                }
            }
        }

        if (empty($productFamily = $product->get('productFamily'))) {
            return true;
        }

        // get product family attributes
        $productFamilyAttributes = $productFamily->get('productFamilyAttributes');

        if (count($productFamilyAttributes) > 0) {
            /** @var \Pim\Repositories\ProductAttributeValue $repository */
            $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');

            foreach ($productFamilyAttributes as $productFamilyAttribute) {
                // create
                $productAttributeValue = $repository->get();
                $productAttributeValue->set(
                    [
                        'productId'                => $product->get('id'),
                        'attributeId'              => $productFamilyAttribute->get('attributeId'),
                        'productFamilyAttributeId' => $productFamilyAttribute->get('id'),
                        'isRequired'               => $productFamilyAttribute->get('isRequired'),
                        'scope'                    => $productFamilyAttribute->get('scope'),
                        'channelId'                => $productFamilyAttribute->get('channelId')
                    ]
                );

                $productAttributeValue->skipVariantValidation = true;
                $productAttributeValue->skipProductChannelValidation = true;

                // save
                try {
                    $this->getEntityManager()->saveEntity($productAttributeValue);
                } catch (ProductAttributeAlreadyExists $e) {
                    $copy = $repository->findCopy($productAttributeValue);
                    $copy->set('productFamilyAttributeId', $productFamilyAttribute->get('id'));
                    $copy->set('isRequired', $productAttributeValue->get('isRequired'));

                    $copy->skipVariantValidation = true;
                    $copy->skipPfValidation = true;
                    $copy->skipProductChannelValidation = true;

                    $this->getEntityManager()->saveEntity($copy);
                }
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

    protected function prepareAssets(Entity $entity, array $result): array
    {
        $channelsIds = array_column($result, 'channel');

        $channels = [];
        if (!empty($channelsIds)) {
            $dbChannels = $this->getEntityManager()->getRepository('Channel')->select(['id', 'name'])->where(['id' => $channelsIds])->find()->toArray();
            $channels = array_column($dbChannels, 'name', 'id');
        }

        foreach ($result as $k => $v) {
            $result[$k]['entityId'] = $entity->get('id');
            $result[$k]['scope'] = 'Global';
            $result[$k]['channelId'] = null;
            $result[$k]['channelName'] = 'Global';
            if (!empty($v['channel']) && !empty($channels[$v['channel']])) {
                $result[$k]['scope'] = 'Channel';
                $result[$k]['channelId'] = $v['channel'];
                $result[$k]['channelName'] = $channels[$v['channel']];
            }
            unset($result[$k]['channel']);
        }

        return $result;
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

    /**
     * @param string $target
     * @param string $action
     * @param Event  $event
     *
     * @return Event
     */
    protected function dispatch(string $target, string $action, Event $event): Event
    {
        return $this->getInjection('eventManager')->dispatch($target, $action, $event);
    }
}
