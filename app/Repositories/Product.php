<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Pim\Core\ValueConverter;

/**
 * Class Product
 */
class Product extends Hierarchy
{
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
     * @param int|null $sorting
     * @param bool $cascadeUpdate
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
     * @param array $ids
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
        $this->addDependency(ValueConverter::class);
    }

    /**
     * @param Entity $entity
     * @param array $options
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

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->translate("youCantChangeFieldOfTypeInProduct", 'exceptions', 'Product'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('ProductAttributeValue')->removeByProductId($entity->get('id'));
        $this->getEntityManager()->getRepository('AssociatedProduct')->removeByProductId($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName === 'classifications') {
            $this->relateClassification($entity, $foreign);
        }

        parent::afterRelate($entity, $relationName, $foreign, $data, $options);
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName === 'classifications') {
            $this->unRelateClassification($entity, $foreign);
        }

        parent::afterUnrelate($entity, $relationName, $foreign, $options);
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

    public function relateClassification($product, $classification): void
    {
        if (is_string($product)) {
            $product = $this->get($product);
        }

        if (is_string($classification)) {
            $classification = $this->getEntityManager()->getRepository('Classification')->get($classification);
        }

        if (!$product instanceof Entity) {
            $GLOBALS['log']->error('RelateClassification Failed: $product is not object');
            return;
        }

        if (!$classification instanceof Entity) {
            $GLOBALS['log']->error('RelateClassification Failed: $classification is not object');
            return;
        }

        $cas = $classification->get('classificationAttributes');
        if (empty($cas[0])) {
            return;
        }

        foreach ($cas as $ca) {
            $productAttributeValue = $this->getEntityManager()->getRepository('ProductAttributeValue')->get();
            $productAttributeValue->set(
                [
                    'productId'      => $product->get('id'),
                    'attributeId'    => $ca->get('attributeId'),
                    'language'       => $ca->get('language'),
                    'scope'          => $ca->get('scope'),
                    'channelId'      => $ca->get('channelId'),
                    'boolValue'      => $ca->get('boolValue'),
                    'dateValue'      => $ca->get('dateValue'),
                    'datetimeValue'  => $ca->get('datetimeValue'),
                    'intValue'       => $ca->get('intValue'),
                    'intValue1'      => $ca->get('intValue1'),
                    'floatValue'     => $ca->get('floatValue'),
                    'floatValue1'    => $ca->get('floatValue1'),
                    'varcharValue'   => $ca->get('varcharValue'),
                    'textValue'      => $ca->get('textValue'),
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

            $productAttributeValue->clearCompletenessFields = true;

            try {
                $this->getEntityManager()->saveEntity($productAttributeValue);
            } catch (BadRequest $e) {
            }
        }
    }

    public function unRelateClassification($product, $classification): void
    {
        $mode = $this->getConfig()->get('behaviorOnClassificationChange', 'retainAllInheritedAttributes');
        if ($mode == 'retainAllInheritedAttributes') {
            return;
        }

        if (is_string($product)) {
            $product = $this->get($product);
        }

        if (is_string($classification)) {
            $classification = $this->getEntityManager()->getRepository('Classification')->get($classification);
        }

        if (!$product instanceof Entity) {
            $GLOBALS['log']->error('UnRelateClassification Failed: $product is not object');
            return;
        }

        if (!$classification instanceof Entity) {
            $GLOBALS['log']->error('UnRelateClassification Failed: $classification is not object');
            return;
        }

        $where = [
            'classificationId' => $classification->get('id')
        ];

        foreach ($product->get('classifications') as $productClassification) {
            if ($productClassification->get('id') === $classification->get('id')) {
                continue;
            }
            foreach ($productClassification->get('classificationAttributes') as $pca) {
                if (!isset($where['attributeId!='])) {
                    $where['attributeId!='] = [];
                }
                $where['attributeId!='] = array_merge($where['attributeId!='], array_column($pca->toArray(), 'attributeId'));
            }
        }

        $cas = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->where($where)
            ->find();

        if (empty($cas[0])) {
            return;
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $product->get('id'), 'attributeId' => array_column($cas->toArray(), 'attributeId')])
            ->find();

        if (empty($pavs[0])) {
            return;
        }

        foreach ($cas as $ca) {
            foreach ($pavs as $pav) {
                if ($pav->get('attributeId') === $ca->get('attributeId') && $pav->get('scope') === $ca->get('scope')) {
                    if ($ca->get('scope') === 'Channel' && $pav->get('channelId') !== $ca->get('channelId')) {
                        continue 1;
                    }

                    $this->getValueConverter()->convertFrom($pav, $pav->get('attribute'));

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

    public function getValueConverter(): ValueConverter
    {
        return $this->getInjection(ValueConverter::class);
    }
}
