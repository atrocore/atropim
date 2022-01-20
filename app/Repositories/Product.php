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
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ChannelAlreadyRelatedToProduct;
use Pim\Core\Exceptions\NoSuchChannelInProduct;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Treo\Core\EventManager\Event;

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

    public function pushJobForUpdateInconsistentAttributes(): void
    {
        $name = $this->translate('updateProductsWithInconsistentAttributes', 'labels', 'Product');
        $this->getInjection('queueManager')->push($name, 'QueueManagerProduct', ['action' => 'updateProductsWithInconsistentAttributes']);
    }

    public function updateProductsAttributes(string $subQuery, bool $createJob = false): void
    {
        $this->getPDO()->exec("UPDATE `product` SET has_inconsistent_attributes=1 WHERE id IN ($subQuery) AND deleted=0");

        if ($createJob) {
            $this->pushJobForUpdateInconsistentAttributes();
        }
    }

    public function updateProductsAttributesViaProductIds(array $productIds, bool $createJob = false): void
    {
        $ids = [];
        foreach ($productIds as $id) {
            $ids[] = $this->getPDO()->quote($id);
        }

        if (!empty($ids)) {
            $this->updateProductsAttributes(implode(',', $ids), $createJob);
        }
    }

    public function updateInconsistentAttributes(Entity $product): void
    {
        if (empty($product->get('hasInconsistentAttributes'))) {
            return;
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $product->get('id')])
            ->find();

        if (count($pavs) === 0) {
            return;
        }

        $languages = [];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            $languages = $this->getConfig()->get('inputLanguageList', []);
        }

        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => array_column($pavs->toArray(), 'attributeId')])->find() as $attribute) {
            $attributes[$attribute->get('id')] = $attribute;
        }

        if (empty($attributes)) {
            return;
        }

        $mainLanguagePavs = new EntityCollection();

        // remove language records
        foreach ($pavs as $pav) {
            if ($pav->get('language') !== 'main') {
                if (!in_array($pav->get('language'), $languages) || empty($attributes[$pav->get('attributeId')]->get('isMultilang'))) {
                    $this->getEntityManager()->removeEntity($pav, ['ignoreLanguages' => true]);
                }
            } else {
                if (!empty($attributes[$pav->get('attributeId')]->get('isMultilang'))) {
                    $mainLanguagePavs->append($pav);
                }
            }
        }

        if (count($mainLanguagePavs) === 0) {
            return;
        }

        /** @var \Pim\Repositories\ProductAttributeValue $pavRepository */
        $pavRepository = $this->getEntityManager()->getRepository('ProductAttributeValue');

        // create language records
        foreach ($mainLanguagePavs as $mainLanguagePav) {
            foreach ($languages as $language) {
                // skip if exist
                foreach ($pavs as $pav) {
                    if ($pav->get('mainLanguageId') === $mainLanguagePav->get('id') && $language === $pav->get('language')) {
                        continue 2;
                    }
                }

                $languagePav = $pavRepository->get();
                $languagePav->set($mainLanguagePav->toArray());
                $languagePav->id = Util::generateId();
                $languagePav->set('mainLanguageId', $mainLanguagePav->get('id'));
                $languagePav->set('language', $language);

                // clear value
                $languagePav->clear('value');
                $languagePav->clear('boolValue');
                $languagePav->clear('dateValue');
                $languagePav->clear('datetimeValue');
                $languagePav->clear('intValue');
                $languagePav->clear('floatValue');
                $languagePav->clear('varcharValue');
                $languagePav->clear('textValue');

                try {
                    $this->getEntityManager()->saveEntity($languagePav);
                } catch (ProductAttributeAlreadyExists $e) {
                    // ignore
                }
            }
        }

        $this->getPDO()->exec("UPDATE `product` SET has_inconsistent_attributes=0 WHERE id='{$product->get('id')}'");
    }

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

    public function getAssetsData(string $productId): array
    {
        $sql
            = "SELECT 
                    pa.asset_id   as assetId, 
                    c.id          as channelId, 
                    c.code        as channelCode 
               FROM product_asset pa 
                   LEFT JOIN channel c ON c.id=pa.channel 
               WHERE pa.deleted=0 
                 AND pa.product_id='$productId'";

        return $this
            ->getEntityManager()
            ->nativeQuery($sql)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRelatedAssetsByType(Entity $entity, string $type): array
    {
        $sql = "SELECT a.*, r.channel as channelId, c.name as channelName, c.code as channelCode, at.id as fileId, at.name as fileName, r.id as relationId
                FROM product_asset r 
                LEFT JOIN asset a ON a.id=r.asset_id
                LEFT JOIN attachment at ON at.id=a.file_id
                LEFT JOIN `channel` c ON c.id=r.channel AND c.deleted=0 AND c.id IN (SELECT channel_id FROM product_channel WHERE product_id='{$entity->get('id')}' AND deleted=0)   
                WHERE 
                      r.deleted=0 
                  AND a.deleted=0 
                  AND a.type='$type' 
                  AND r.product_id='{$entity->get('id')}' 
                ORDER BY r.sorting ASC";

        $result = $this->getEntityManager()->getRepository('Asset')->findByQuery($sql)->toArray();

        $prismChannelId = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->getPrismChannelId();

        $hasChannelMainImage = !empty($prismChannelId) && isset(array_column($entity->getMainImages(), 'attachmentId', 'channelId')[$prismChannelId]);

        foreach ($result as $k => $v) {
            // filter via product channels
            if (!empty($v['channelId']) && empty($v['channelName'])) {
                unset($result[$k]);
            }

            // filter via channel prism
            if (!empty($prismChannelId) && !empty($v['channelId']) && $v['channelId'] !== $prismChannelId) {
                unset($result[$k]);
            }
        }

        if (empty($result)) {
            return [];
        }

        foreach ($result as $k => $v) {
            $result[$k]['entityName'] = $entity->getEntityType();
            $result[$k]['entityId'] = $entity->get('id');
            $result[$k]['scope'] = !empty($v['channelId']) ? 'Channel' : 'Global';
            $result[$k]['channels'] = [];

            if ($this->isImage($result[$k]['fileName'])) {
                $result[$k]['isImage'] = true;
                foreach ($entity->getMainImages() as $row) {
                    if ($row['attachmentId'] === $result[$k]['fileId']) {
                        $result[$k]['isMainImage'] = true;
                        $result[$k]['isGlobalMainImage'] = $row['scope'] === 'Global';
                        if (!empty($row['channelId'])) {
                            $result[$k]['channels'][] = $row['channelId'];
                        }
                    }
                }
            }

            if (!empty($result[$k]['channels'])) {
                $result[$k]['isMainImage'] = false;
            }

            if (!empty($prismChannelId) && $hasChannelMainImage) {
                $result[$k]['isMainImage'] = $result[$k]['isGlobalMainImage'] = in_array($prismChannelId, $result[$k]['channels']);
            }

            $result[$k]['channel'] = empty($v['channelName']) ? '-' : $v['channelId'];

            if (!empty($result[$k]['channelId'])) {
                $result[$k]['id'] = $result[$k]['id'] . '_' . $result[$k]['channelId'];
            }
        }

        return array_values($result);
    }

    public function updateSortOrder(string $entityId, array $ids): bool
    {
        foreach ($ids as $k => $id) {
            $parts = explode('_', $id);
            $id = array_shift($parts);
            $channel = implode('_', $parts);

            $sorting = $k * 10;

            $sql = "UPDATE product_asset SET sorting=$sorting WHERE asset_id='$id' AND product_id='$entityId' AND deleted=0";
            if (empty($channel)) {
                $sql .= " AND (channel IS NULL OR channel='')";
            } else {
                $sql .= " AND channel='$channel'";
            }
            $this->getEntityManager()->nativeQuery($sql);
        }

        return true;
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
            if (isset($params['additionalColumns']['pcSorting'])) {
                unset($params['additionalColumns']['pcSorting']);
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
     * @param Entity|string $product
     * @param Entity|string $category
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
                    $this->relateChannel($product, $channel, true);
                } else {
                    $this->unrelateChannel($product, $channel);
                }
            }
        }

        return true;
    }

    public function relateChannel(Entity $product, Entity $channel, bool $fromCategoryTree = false): bool
    {
        $product->fromCategoryTree = $fromCategoryTree;
        try {
            $this->relate($product, 'channels', $channel);
        } catch (ChannelAlreadyRelatedToProduct $e) {
            $this->updateChannelRelationData($product, $channel, null, true);
        }

        return true;
    }

    public function unrelateChannel(Entity $product, Entity $channel): bool
    {
        $product->skipIsFromCategoryTreeValidation = true;
        return $this->unrelateForce($product, 'channels', $channel);
    }

    public function unrelateCategoryTreeChannels(Entity $product): bool
    {
        $this->getPDO()->exec("DELETE FROM product_channel WHERE deleted=0 AND product_id='{$product->get('id')}' AND from_category_tree=1");
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
            ->where(['productFamilyId' => $product->get('productFamilyId'), 'channelId' => $channelId])
            ->find();

        foreach ($pfas as $pfa) {
            $this->getEntityManager()->getRepository('ProductFamilyAttribute')->createProductAttributeValue($pfa, $product);
        }

        $this->updateProductsAttributesViaProductIds([$product->get('id')]);
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
            throw $e;
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

        if ($entity->isAttributeChanged('imageId') && !empty($image = $entity->get('image')) && !empty($asset = $image->getAsset())) {
            $this->relate($entity, 'assets', $asset->get('id'));
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
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $entity->get('id')])
            ->find();

        foreach ($pavs as $pav) {
            $this->getEntityManager()->removeEntity($pav, ['skipProductAttributeValueHook' => true]);
        }

        $associations = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->where([
                'OR' => [
                    ['mainProductId' => $entity->id],
                    ['relatedProductId' => $entity->id]
                ]
            ])
            ->find();

        if (count($associations) > 0) {
            foreach ($associations as $association) {
                $this->getEntityManager()->removeEntity($association);
            }
        }

        parent::afterRemove($entity, $options);
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'categories') {
            if (is_bool($foreign)) {
                throw new BadRequest('Action blocked. Please, specify Category that we should be related with Product.');
            }

            if (is_string($foreign)) {
                $foreign = $this->getEntityManager()->getEntity('Category', $foreign);
            }

            $this->isCategoryAlreadyRelated($entity, $foreign);
            $this->isCategoryFromCatalogTrees($entity, $foreign);
            $this->isProductCanLinkToNonLeafCategory($foreign);
        }

        if ($relationName == 'channels') {
            if (!$entity->isSkippedValidation('isChannelAlreadyRelated')) {
                $this->isChannelAlreadyRelated($entity, $foreign);
            }
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'categories') {
            $this->updateProductCategorySortOrder($entity, $foreign);
            $this->linkCategoryChannels($entity, $foreign);
        }

        if ($relationName == 'channels') {
            // set from_category_tree param
            if (!empty($entity->fromCategoryTree)) {
                $this->updateChannelRelationData($entity, $foreign, null, true);
            }
            $this->relatePfas($entity, $foreign);
        }

        parent::afterRelate($entity, $relationName, $foreign, $data, $options);
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'channels' && empty($entity->skipIsFromCategoryTreeValidation)) {
            $productId = (string)$entity->get('id');
            $channelId = is_string($foreign) ? $foreign : (string)$foreign->get('id');

            $channelRelationData = $this->getChannelRelationData($productId);

            if (!empty($channelRelationData[$channelId]['isFromCategoryTree'])) {
                throw new BadRequest($this->translate("channelProvidedByCategoryTreeCantBeUnlinkedFromProduct", 'exceptions', 'Product'));
            }
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'categories') {
            $this->linkCategoryChannels($entity, $foreign, true);
        }

        if ($relationName == 'channels') {
            $this->getEntityManager()->nativeQuery("DELETE FROM product_channel WHERE deleted=1");
            $this->unrelatePfas($entity, $foreign);
        }

        parent::afterUnrelate($entity, $relationName, $foreign, $options);
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
