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

/**
 * Class Category
 */
class Category extends AbstractRepository
{
    public function findRelatedAssetsByType(Entity $entity, string $type): array
    {
        $id = $entity->get('id');

        $sql = "SELECT a.*, r.channel, at.id as fileId, at.name as fileName
                FROM category_asset r 
                LEFT JOIN asset a ON a.id=r.asset_id
                LEFT JOIN attachment at ON at.id=a.file_id 
                WHERE 
                      r.deleted=0 
                  AND a.deleted=0 
                  AND a.type='$type' 
                  AND r.category_id='$id' 
                ORDER BY r.sorting ASC";

        $result = $this->getEntityManager()->getRepository('Asset')->findByQuery($sql)->toArray();

        return $this->prepareAssets($entity, $result);
    }

    public function canUnRelateCatalog(Entity $category, Entity $catalog): void
    {
        /** @var array $productsIds */
        $productsIds = array_column($catalog->get('products')->toArray(), 'id');

        if (!empty($productsIds)) {
            $categoriesIds = array_column($category->getChildren()->toArray(), 'id');
            $categoriesIds[] = $category->get('id');

            $categoriesIds = implode("','", $categoriesIds);
            $productsIds = implode("','", $productsIds);

            $total = $this
                ->getEntityManager()
                ->nativeQuery("SELECT COUNT('id') as total FROM product_category WHERE product_id IN ('$productsIds') AND category_id IN ('$categoriesIds') AND deleted=0")
                ->fetch(\PDO::FETCH_COLUMN);

            if (!empty($total)) {
                throw new BadRequest($this->exception('categoryCannotBeUnRelatedFromCatalog'));
            }
        }
    }

    /**
     * @param Entity|string $category
     * @param Entity|string $catalog
     */
    public function tryToUnRelateCatalog($category, $catalog): void
    {
        if (!$category instanceof Entity) {
            $category = $this->getEntityManager()->getEntity('Category', $category);
        }

        if (!$catalog instanceof Entity) {
            $catalog = $this->getEntityManager()->getEntity('Catalog', $catalog);
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') !== 'cascade') {
            $this->canUnRelateCatalog($category, $catalog);
        } else {
            $this->cascadeUnRelateCatalog($category, $catalog);
        }
    }

    public function cascadeUnRelateCatalog(Entity $category, Entity $catalog): void
    {
        $products = $catalog->get('products');
        if (count($products) > 0) {
            $root = $category->getRoot();
            $children = $root->getChildren();
            foreach ($products as $product) {
                $this->getEntityManager()->getRepository('Product')->unrelate($product, 'categories', $root);
                if (count($children) > 0) {
                    foreach ($children as $cat) {
                        $this->getEntityManager()->getRepository('Product')->unrelate($product, 'categories', $cat);
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entity->set('sortOrder', time());
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId')) {
            if (empty($entity->getFetched('categoryParentId'))) {
                throw new BadRequest($this->exception('thereIsNoAbilityToChangeCategoryParentForRootCategory'));
            }

            // get fetched category
            $fetchedCategory = $this->getEntityManager()->getEntity('Category', $entity->getFetched('categoryParentId'));

            if (empty($entity->get('categoryParentId')) || $entity->get('categoryParent')->getRoot()->get('id') != $fetchedCategory->getRoot()->get('id')) {
                throw new BadRequest($this->exception('thereIsNoAbilityToChangeCategoryTree'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('_position'))) {
            $this->updateSortOrderInTree($entity);
        }

        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') !== 'cascade') {
            if ($entity->get('products')->count() > 0) {
                throw new BadRequest($this->exception("categoryHasProducts"));
            }

            if ($entity->get('categories')->count() > 0) {
                throw new BadRequest($this->exception("categoryHasChildCategoryAndCantBeDeleted"));
            }
        } else {
            $products = $entity->get('products');
            if (count($products) > 0) {
                $channels = $entity->getRoot()->get('channels');
                foreach ($products as $product) {
                    $this->unrelate($entity, 'products', $product);
                    if (count($channels) > 0) {
                        foreach ($channels as $channel) {
                            $this->getEntityManager()->getRepository('Product')->unrelate($product, 'channels', $channel);
                        }
                    }
                }
            }

            $children = $entity->get('categories');
            if (count($children) > 0) {
                foreach ($children as $child) {
                    $this->getEntityManager()->removeEntity($child);
                }
            }
        }

        parent::beforeRemove($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'catalogs') {
            if (!empty($entity->get('categoryParent'))) {
                throw new BadRequest($this->exception('Only root category can be linked with catalog'));
            }
        }

        if ($relationName == 'products') {
            $this->getEntityManager()->getRepository('Product')->isCategoryFromCatalogTrees($foreign, $entity);
            $this->getEntityManager()->getRepository('Product')->isProductCanLinkToNonLeafCategory($entity);
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'catalogs') {
            $this->tryToUnRelateCatalog($entity, $foreign);
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    /**
     * @param Entity $entity
     */
    protected function updateSortOrderInTree(Entity $entity): void
    {
        // prepare sort order
        $sortOrder = 0;

        // prepare data
        $data = [];

        if ($entity->get('_position') == 'after') {
            // prepare sort order
            $sortOrder = $this->select(['sortOrder'])->where(['id' => $entity->get('_target')])->findOne()->get('sortOrder');

            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => [$entity->get('_target'), $entity->get('id')],
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();

            // increase sort order
            $sortOrder = $sortOrder + 10;

        } elseif ($entity->get('_position') == 'inside') {
            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => $entity->get('id'),
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();
        }

        // prepare data
        $data = array_merge([$entity->get('id')], array_column($data, 'id'));

        // prepare sql
        $sql = '';
        foreach ($data as $id) {
            // prepare sql
            $sql .= "UPDATE category SET sort_order=$sortOrder WHERE id='$id';";

            // increase sort order
            $sortOrder = $sortOrder + 10;
        }

        // execute sql
        $this->getEntityManager()->nativeQuery($sql);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'Category');
    }
}
