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
                    $this->relate($product, 'channels', $channel);
                } else {
                    $product->skipIsFromCategoryTreeValidation = true;
                    $this->unrelate($product, 'channels', $channel);
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
     * @inheritdoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        // save attributes
        $this->saveAttributes($entity);

        // parent action
        parent::afterSave($entity, $options);
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
}
