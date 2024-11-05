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
use Atro\Core\EventManager\Event;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Core\ValueConverter;

class Product extends Hierarchy
{
    public function getProductsIdsViaAccountId(string $accountId): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->distinct()
            ->from($this->getConnection()->quoteIdentifier('product_channel'), 'pc')
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier('product'), 'p', 'pc.product_id=p.id AND p.deleted=:false')
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier('channel'), 'c', 'pc.channel_id=c.id AND c.deleted=:false')
            ->innerJoin('pc', $this->getConnection()->quoteIdentifier('account'), 'a', 'a.channel_id=c.id AND a.deleted=:false')
            ->where('pc.deleted=:false')
            ->andWhere('a.id=:id')
            ->setParameter('id', $accountId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }

    public function getCategoriesChannelsIds(string $productId): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('cc.channel_id')
            ->from($this->getConnection()->quoteIdentifier('category_channel'), 'cc')
            ->where('cc.deleted=:false')
            ->andWhere(
                "cc.category_id IN (SELECT pc.category_id FROM {$this->getConnection()->quoteIdentifier('product_category')} pc WHERE pc.deleted=:false AND pc.product_id=:productId)"
            )
            ->setParameter('productId', $productId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($res, 'channel_id');
    }

    public function getInputLanguageList(): array
    {
        return $this->getConfig()->get('inputLanguageList', []);
    }

    public function unlinkProductsFromNonLeafCategories(): void
    {
        $data = $this->getConnection()->createQueryBuilder()
            ->select('product_id, category_id')
            ->from('product_category')
            ->where('category_id IN (SELECT DISTINCT parent_id FROM category_hierarchy where deleted=:false)')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        foreach ($data as $row) {
            $product = $this->get($row['product_id']);
            $category = $this->getEntityManager()->getRepository('Category')->get($row['category_id']);
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
            $params['leftJoins'] = ['attribute'];
            $params['sortBy'] = 'attribute_mm.sortOrderInProduct';
            $params['asc'] = true;
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
    public function isProductCanLinkToNonLeafCategory(string $categoryId): bool
    {
        if ($this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            return true;
        }

        if ($this->getEntityManager()->getRepository('Category')->hasChildren($categoryId)) {
            throw new BadRequest($this->translate("productCanNotLinkToNonLeafCategory", 'exceptions', 'Product'));
        }

        return true;
    }

    public function getProductCategoryLinkData(array $productsIds, array $categoriesIds): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('pc.*')
            ->from($this->getConnection()->quoteIdentifier('product_category'), 'pc')
            ->where('pc.product_id IN (:productsIds)')
            ->andWhere('pc.category_id IN (:categoriesIds)')
            ->andWhere('pc.deleted = :false')
            ->setParameter('productsIds', $productsIds, Mapper::getParameterType($productsIds))
            ->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->orderBy('sorting','DESC')
            ->fetchAllAssociative();
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
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('product_category'), 'pc')
            ->set('sorting', ':sorting')
            ->where('pc.category_id = :categoryId')
            ->andWhere('pc.product_id = :productId')
            ->andWhere('pc.deleted = :false')
            ->setParameter('sorting', $sorting, Mapper::getParameterType($sorting))
            ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
            ->setParameter('productId', $productId, Mapper::getParameterType($productId))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->executeQuery();

        if ($cascadeUpdate) {
            // get next records
            $res = $this->getConnection()->createQueryBuilder()
                ->select('pc.id')
                ->from($this->getConnection()->quoteIdentifier('product_category'), 'pc')
                ->where('pc.sorting >= :sorting')
                ->andWhere('pc.category_id = :categoryId')
                ->andWhere('pc.deleted = :false')
                ->andWhere('pc.product_id != :productId')
                ->setParameter('sorting', $sorting, Mapper::getParameterType($sorting))
                ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
                ->setParameter('productId', $productId, Mapper::getParameterType($productId))
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->orderBy('pc.sorting', 'ASC')
                ->fetchAllAssociative();

            $ids = array_column($res, 'id');

            // update next records
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $max = $max + 10;

                    $this->getConnection()->createQueryBuilder()
                        ->update($this->getConnection()->quoteIdentifier('product_category'), 'pc')
                        ->set('sorting', ':sorting')
                        ->where('pc.id = :id')
                        ->setParameter('sorting', $max, Mapper::getParameterType($max))
                        ->setParameter('id', $id, Mapper::getParameterType($id))
                        ->executeQuery();
                }
            }
        }
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
        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;

            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('product_category'), 'pc')
                ->set('sorting', ':sorting')
                ->where('pc.product_id = :productId')
                ->andWhere('pc.category_id = :categoryId')
                ->andWhere('pc.deleted = :false')
                ->setParameter('sorting', $sortOrder, Mapper::getParameterType($sortOrder))
                ->setParameter('productId', $id, Mapper::getParameterType($id))
                ->setParameter('categoryId', $categoryId, Mapper::getParameterType($categoryId))
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->executeQuery();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
        $this->addDependency('queueManager');
        $this->addDependency(ValueConverter::class);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('catalogId')) {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->{"onCatalog{$mode}Change"}($entity, $entity->get('catalog'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->translate("youCantChangeFieldOfTypeInProduct", 'exceptions', 'Product'));
        }

        if($this->getConfig()->get('allowSingleClassificationForProduct', false)
            && $entity->isAttributeChanged('classificationsIds') && count($entity->get('classificationsIds')) > 1){
            $data = $entity->get('classificationsIds');
            $entity->set('classificationsIds', [end($data)]);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('ProductAttributeValue')->removeByProductId($entity->get('id'));
        $this->getEntityManager()->getRepository('AssociatedProduct')->removeByProductId($entity->get('id'));
        $this->getEntityManager()->getRepository('ProductFile')->removeByProductId($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()
            ->createQueryBuilder()
            ->update('product_attribute_value')
            ->set('deleted',':false')
            ->where('product_id = :productId')
            ->andWhere('deleted = :true')
            ->setParameter('false',false, ParameterType::BOOLEAN)
            ->setParameter('productId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('true',true, ParameterType::BOOLEAN)
            ->executeStatement();

        $this->getConnection()
            ->createQueryBuilder()
            ->update('associated_product')
            ->set('deleted',':false')
            ->where('main_product_id = :productId')
            ->orWhere('related_product_id = :productId')
            ->andWhere('deleted = :true')
            ->setParameter('false',false, ParameterType::BOOLEAN)
            ->setParameter('productId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('true',true, ParameterType::BOOLEAN)
            ->executeStatement();
    }


    public function getProductsHierarchyMap(array $productIds): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('t.entity_id, t.parent_id')
            ->from($this->getConnection()->quoteIdentifier('product_hierarchy'), 't')
            ->where('t.entity_id IN (:ids)')
            ->andWhere('t.deleted = :false')
            ->setParameter('ids', $productIds, Mapper::getParameterType($productIds))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        $result = [];
        foreach ($res as $row) {
            $result[] = [
                'childId'   => $row['entity_id'],
                'parent_id' => $row['parentId']
            ];
        }

        return $result;
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
                    'referenceValue' => $ca->get('referenceValue'),
                    'textValue'      => $ca->get('textValue'),
                ]
            );

            $productAttributeValue->clearCompletenessFields = true;

            $attribute = $ca->get('attribute');
            if (!empty($attribute) && $attribute->get('type') === 'linkMultiple') {
                $linkName = $attribute->getLinkMultipleLinkName();
                $productAttributeValue->set('valueIds', $ca->getLinkMultipleIdList($linkName));
            }

            try {
                $this->getEntityManager()->saveEntity($productAttributeValue, ['ignoreDuplicate' => true]);
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
                if ($pav->get('attributeId') === $ca->get('attributeId') && $pav->get('channelId') === $ca->get('channelId')) {
                    $this->getValueConverter()->convertFrom($pav, $pav->get('attribute'));
                    if ($mode === 'removeOnlyInheritedAttributesWithNoValue') {
                        if ($pav->get('value') !== null && $pav->get('value') !== '') {
                            continue 1;
                        }
                    }
                    $pav->clearCompletenessFields = true;
                    $this->getEntityManager()->removeEntity($pav);
                }
            }
        }
    }

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
