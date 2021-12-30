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

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Pim\Services\GeneralStatisticsDashlet;
use Pim\Entities\Attribute;

/**
 * Product select manager
 */
class Product extends AbstractSelectManager
{
    /**
     * @var string
     */
    protected $customWhere = '';

    private array $attributes = [];

    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        // filtering by product types
        $params['where'][] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => array_keys($this->getMetadata()->get('pim.productType', []))
        ];

        // filtering by categories
        $this->filteringByCategories($params);

        // get product attributes filter
        $productAttributes = $this->getProductAttributeFilter($params);

        // get select params
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        // prepare custom where
        $selectParams['customWhere'] .= $this->customWhere;

        // add product attributes filter
        if (!empty($productAttributes)) {
            $this->addProductAttributesFilter($selectParams, $productAttributes);
        }

        // for products in category page
        if ($params['sortBy'] == 'pcSorting') {
            $selectParams['additionalColumns']['sorting'] = 'pcSorting';
            $selectParams['orderBy'] = 'product_category.sorting';
        }

        return $selectParams;
    }

    /**
     * @inheritDoc
     */
    protected function textFilter($textFilter, &$result)
    {
        parent::textFilter($textFilter, $result);

        if (empty($result['whereClause'])) {
            return;
        }

        $last = array_pop($result['whereClause']);

        if (!isset($last['OR'])) {
            return;
        }

        $textFilter = $textFilter . '%';
        if (mb_strpos($textFilter, 'ft:') === 0) {
            $textFilter = mb_substr($textFilter, 3);
        }
        if (mb_strpos($textFilter, '*') !== false) {
            $textFilter = str_replace('*', '%', $textFilter);
        }

        // find product attribute values
        $pavData = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['productId', 'scope', 'channelId'])
            ->where([
                'attributeType' => ['varchar', 'text', 'wysiwyg', 'enum'],
                ['OR' => [['varcharValue*' => $textFilter], ['textValue*' => $textFilter]]],
            ])
            ->find()
            ->toArray();

        // find product channels
        $productChannels = $this->getProductsChannels(array_column($pavData, 'productId'));

        // filtering products
        foreach ($pavData as $row) {
            if ($row['scope'] == 'Channel' && (!isset($productChannels[$row['productId']]) || !in_array($row['channelId'], $productChannels[$row['productId']]))) {
                continue 1;
            }
            $productsIds[] = $row['productId'];
        }

        if (!empty($productsIds)) {
            $last['OR']['id'] = $productsIds;
        }

        $result['whereClause'][] = $last;
    }

    /**
     * Products without any attributes
     *
     * @param $result
     */
    protected function boolFilterWithoutProductAttributes(&$result)
    {
        $result['customWhere'] .= " AND product.id NOT IN (SELECT DISTINCT product_id FROM product_attribute_value WHERE deleted=0)";
    }

    /**
     * Products without associated products filter
     *
     * @param $result
     */
    protected function boolFilterWithoutAssociatedProducts(&$result)
    {
        $result['whereClause'][] = [
            'id' => array_column($this->getProductWithoutAssociatedProduct(), 'id')
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyCatalogProducts(&$result)
    {
        if (!empty($category = $this->getEntityManager()->getEntity('Category', (string)$this->getSelectCondition('notLinkedWithCategory')))) {
            // prepare ids
            $ids = ['-1'];

            // get root id
            if (empty($category->get('categoryParent'))) {
                $rootId = $category->get('id');
            } else {
                $tree = explode("|", (string)$category->get('categoryRoute'));
                $rootId = (!empty($tree[1])) ? $tree[1] : null;
            }

            if (!empty($rootId)) {
                $catalogs = $this
                    ->getEntityManager()
                    ->getRepository('Catalog')
                    ->distinct()
                    ->join('categories')
                    ->where(['categories.id' => $rootId])
                    ->find();

                if (count($catalogs) > 0) {
                    foreach ($catalogs as $catalog) {
                        $ids = array_merge($ids, array_column($catalog->get('products')->toArray(), 'id'));
                    }
                }
            }

            // prepare where
            $result['whereClause'][] = [
                'id' => $ids
            ];
        }
    }

    /**
     * Get product without AssociatedProduct
     *
     * @return array
     */
    protected function getProductWithoutAssociatedProduct(): array
    {
        return $this->fetchAll($this->getGeneralStatisticService()->getQueryProductWithoutAssociatedProduct());
    }

    /**
     * Products without Category filter
     *
     * @param $result
     */
    protected function boolFilterWithoutAnyCategory(&$result)
    {
        $result['whereClause'][] = [
            'id' => array_column($this->getProductWithoutCategory(), 'id')
        ];
    }

    /**
     * Get product without Category
     *
     * @return array
     */
    protected function getProductWithoutCategory(): array
    {
        return $this->fetchAll($this->getGeneralStatisticService()->getQueryProductWithoutCategory());
    }

    /**
     * Products without Image filter
     *
     * @param $result
     */
    protected function boolFilterWithoutImageAssets(&$result)
    {
        $result['whereClause'][] = [
            'id' => array_column($this->getProductWithoutImageAssets(), 'id')
        ];
    }

    /**
     * Get products without Image
     *
     * @return array
     */
    protected function getProductWithoutImageAssets(): array
    {
        return $this->fetchAll($this->getGeneralStatisticService()->getQueryProductWithoutImage());
    }

    /**
     * NotAssociatedProduct filter
     *
     * @param array $result
     */
    protected function boolFilterNotAssociatedProducts(&$result)
    {
        // prepare data
        $data = (array)$this->getSelectCondition('notAssociatedProducts');

        if (!empty($data['associationId'])) {
            $associatedProducts = $this->getAssociatedProducts($data['associationId'], $data['mainProductId']);
            foreach ($associatedProducts as $row) {
                $result['whereClause'][] = [
                    'id!=' => (string)$row['related_product_id']
                ];
            }
        }
    }

    /**
     * OnlySimple filter
     *
     * @param array $result
     */
    protected function boolFilterOnlySimple(&$result)
    {
        $result['whereClause'][] = [
            'type' => 'simpleProduct'
        ];
    }

    protected function boolFilterOnlyCategoryCatalogsProducts(array &$result): void
    {
        $categoryId = $this->getSelectCondition('onlyCategoryCatalogsProducts');
        if (empty($categoryId) || empty($category = $this->getEntityManager()->getEntity('Category', $categoryId))) {
            throw new BadRequest('No such category');
        }

        $catalogs = $category->getRoot()->get('catalogs');
        if (count($catalogs) > 0) {
            $result['whereClause'][] = ['catalogId' => array_column($catalogs->toArray(), 'id')];
        } else {
            $result['whereClause'][] = ['catalogId' => null];
        }
    }

    /**
     * Get assiciated products
     *
     * @param string $associationId
     * @param string $productId
     *
     * @return array
     */
    protected function getAssociatedProducts($associationId, $productId)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql
            = 'SELECT
          related_product_id
        FROM
          associated_product
        WHERE
          main_product_id =' . $pdo->quote($productId) . '
          AND association_id = ' . $pdo->quote($associationId) . '
          AND deleted = 0';

        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * NotLinkedWithChannel filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithChannel(&$result)
    {
        $channelId = (string)$this->getSelectCondition('notLinkedWithChannel');

        if (!empty($channelId)) {
            $channelProducts = $this->createService('Channel')->getProducts($channelId);
            foreach ($channelProducts as $row) {
                $result['whereClause'][] = [
                    'id!=' => (string)$row['productId']
                ];
            }
        }
    }

    /**
     * ActiveForChannel filter
     *
     * @param array $result
     */
    protected function boolFilterActiveForChannel(&$result)
    {
        $channelId = (string)$this->getSelectCondition('activeForChannel');

        if (empty($channelId)) {
            $channelId = 'no-such-id';
        }

        $result['customWhere'] .= " AND product.id IN (SELECT product_id FROM `product_channel` WHERE deleted=0 AND is_active=1 AND channel_id='$channelId')";
    }

    /**
     * NotLinkedWithBrand filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithBrand(array &$result)
    {
        // prepare data
        $brandId = (string)$this->getSelectCondition('notLinkedWithBrand');

        if (!empty($brandId)) {
            // get Products linked with brand
            $products = $this->getBrandProducts($brandId);
            foreach ($products as $row) {
                $result['whereClause'][] = [
                    'id!=' => $row['productId']
                ];
            }
        }
    }

    /**
     * Get productIds related with brand
     *
     * @param string $brandId
     *
     * @return array
     */
    protected function getBrandProducts(string $brandId): array
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql
            = 'SELECT id AS productId
                FROM product
                WHERE deleted = 0 
                      AND brand_id = :brandId';

        $sth = $pdo->prepare($sql);
        $sth->execute(['brandId' => $brandId]);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * NotLinkedWithProductFamily filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProductFamily(array &$result)
    {
        // prepare data
        $productFamilyId = (string)$this->getSelectCondition('notLinkedWithProductFamily');

        if (!empty($productFamilyId)) {
            // get Products linked with brand
            $products = $this->getProductFamilyProducts($productFamilyId);
            foreach ($products as $row) {
                $result['whereClause'][] = [
                    'id!=' => $row['productId']
                ];
            }
        }
    }

    /**
     * Get productIds related with productFamily
     *
     * @param string $productFamilyId
     *
     * @return array
     */
    protected function getProductFamilyProducts(string $productFamilyId): array
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql
            = 'SELECT id AS productId
                FROM product
                WHERE deleted = 0
                      AND product_family_id = :productFamilyId';

        $sth = $pdo->prepare($sql);
        $sth->execute(['productFamilyId' => $productFamilyId]);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * NotLinkedWithPackaging filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithPackaging(&$result)
    {
        // find products
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(
                [
                    'packagingId' => (string)$this->getSelectCondition('notLinkedWithPackaging')
                ]
            )
            ->find();

        if (!empty($products)) {
            foreach ($products as $product) {
                $result['whereClause'][] = [
                    'id!=' => $product->get('id')
                ];
            }
        }
    }

    /**
     * Fetch all result from DB
     *
     * @param string $query
     *
     * @return array
     */
    protected function fetchAll(string $query): array
    {
        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create dashlet service
     *
     * @return GeneralStatisticsDashlet
     */
    protected function getGeneralStatisticService(): GeneralStatisticsDashlet
    {
        return $this->createService('GeneralStatisticsDashlet');
    }

    /**
     * NotLinkedWithProductSerie filter
     *
     * @param $result
     */
    protected function boolFilterNotLinkedWithProductSerie(&$result)
    {
        //find products
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->join(['productSerie'])
            ->where(
                [
                    'productSerie.id' => (string)$this->getSelectCondition('notLinkedWithProductSerie')
                ]
            )
            ->find();

        // add product ids to whereClause
        if (!empty($products)) {
            foreach ($products as $product) {
                $result['whereClause'][] = [
                    'id!=' => $product->get('id')
                ];
            }
        }
    }

    /**
     * @param array $result
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterLinkedWithCategory(array &$result)
    {
        if (empty($id = $this->getSelectCondition('linkedWithCategory'))) {
            return;
        }

        if (empty($category = $this->getEntityManager()->getEntity('Category', $id))) {
            throw new BadRequest('No such category');
        }

        // collect categories
        $categoriesIds = array_column($category->getChildren()->toArray(), 'id');
        $categoriesIds[] = $category->get('id');

        // prepare categories ids
        $ids = implode("','", $categoriesIds);

        // set custom where
        $result['customWhere'] .= " AND product.id IN (SELECT product_id FROM product_category WHERE product_id IS NOT NULL AND deleted=0 AND category_id IN ('$ids'))";
    }

    protected function boolFilterLinkedWithProductFamily(array &$result)
    {
        \Pim\Repositories\ProductFamily::onlyForAdvancedClassification();

        if (empty($id = $this->getSelectCondition('linkedWithProductFamily'))) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository('ProductFamily');
        if (empty($pf = $repository->get($id))) {
            throw new BadRequest('No such Product Family');
        }

        $ids = $repository->getChildrenIds($pf);
        $ids[] = $pf->get('id');

        $result['whereClause'][] = [
            'productFamilyId' => $ids
        ];
    }

    /**
     * @param array $params
     *
     * @return array
     */
    protected function getProductAttributeFilter(array &$params): array
    {
        $result = [];

        if (!empty($params['where']) && is_array($params['where'])) {
            $where = [];
            foreach ($params['where'] as $row) {
                if (!empty($row['isAttribute']) || !empty($row['value'][0]['isAttribute'])) {
                    $result[] = $row;
                } else {
                    $where[] = $row;
                }
            }
            $params['where'] = $where;
        }

        return $result;
    }

    protected function getAttribute(string $id): Attribute
    {
        if (!isset($this->attributes[$id])) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $id);
            if (empty($attribute)) {
                throw new NotFound();
            }
            $this->attributes[$id] = $attribute;
        }

        return $this->attributes[$id];
    }

    protected function convertAttributeWhere(array $row): array
    {
        if (in_array($row['type'], ['or', 'and']) && !empty($row['value'])) {
            foreach ($row['value'] as $k => $v) {
                $row['value'][$k] = $this->convertAttributeWhere($v);
            }
            return $row;
        }

        $attribute = $this->getAttribute($row['attribute']);

        if (isset($row['isAttribute'])) {
            unset($row['isAttribute']);
        }

        $where = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'equals',
                    'attribute' => 'attributeId',
                    'value'     => $attribute->get('id')
                ],
            ]
        ];

        switch ($attribute->get('type')) {
            case 'array':
            case 'multiEnum':
                if ($row['type'] === 'arrayIsEmpty') {
                    $where['value'][] = [
                        'type'  => 'or',
                        'value' => [
                            [
                                'type'      => 'isNull',
                                'attribute' => 'textValue'
                            ],
                            [
                                'type'      => 'equals',
                                'attribute' => 'textValue',
                                'value'     => ''
                            ],
                            [
                                'type'      => 'equals',
                                'attribute' => 'textValue',
                                'value'     => '[]'
                            ]
                        ]
                    ];
                } elseif ($row['type'] === 'arrayIsNotEmpty') {
                    $where['value'][] = [
                        'type'  => 'or',
                        'value' => [
                            [
                                'type'      => 'isNotNull',
                                'attribute' => 'textValue'
                            ],
                            [
                                'type'      => 'notEquals',
                                'attribute' => 'textValue',
                                'value'     => ''
                            ],
                            [
                                'type'      => 'notEquals',
                                'attribute' => 'textValue',
                                'value'     => '[]'
                            ]
                        ]
                    ];
                } else {
                    $where['value'][] = [
                        'type'  => 'or',
                        'value' => []
                    ];

                    $values = (empty($row['value'])) ? [md5('no-such-value-' . time())] : $row['value'];
                    foreach ($values as $value) {
                        $where['value'][1]['value'][] = [
                            'type'      => 'like',
                            'attribute' => 'textValue',
                            'value'     => "%\"$value\"%"
                        ];
                    }
                }
                break;
            case 'text':
            case 'wysiwyg':
                $row['attribute'] = 'textValue';
                $where['value'][] = $row;
                break;
            case 'bool':
                $row['attribute'] = 'boolValue';
                $where['value'][] = $row;
                break;
            case 'currency':
            case 'unit':
                // @todo we should do it in the future
                break;
            case 'int':
                $row['attribute'] = 'intValue';
                $where['value'][] = $row;
                break;
            case 'float':
                $row['attribute'] = 'floatValue';
                $where['value'][] = $row;
                break;
            case 'date':
                $row['attribute'] = 'dateValue';
                $where['value'][] = $row;
                break;
            case 'datetime':
                $row['attribute'] = 'datetimeValue';
                $where['value'][] = $row;
                break;
            case 'enum':
                $row['attribute'] = 'varcharValue';
                $where['value'][] = $row;
                $where['value'][] = [
                    'type'      => 'equals',
                    'attribute' => 'language',
                    'value'     => 'main',
                ];
                break;
            default:
                $row['attribute'] = 'varcharValue';
                $where['value'][] = $row;
                break;
        }

        if ($attribute->get('type') === 'multiEnum') {
            $where['value'][] = [
                'type'      => 'equals',
                'attribute' => 'language',
                'value'     => 'main',
            ];
        }

        return $where;
    }

    protected function addProductAttributesFilter(array &$selectParams, array $attributes): void
    {
        foreach ($attributes as $row) {
            $sp = $this->createSelectManager('ProductAttributeValue')->getSelectParams(['where' => [$this->convertAttributeWhere($row)]], true, true);
            $sp['select'] = ['productId', 'scope', 'channelId'];

            // get product attribute values
            $pavData = $this
                ->getEntityManager()
                ->getPDO()
                ->query($this->getEntityManager()->getQuery()->createSelectQuery('ProductAttributeValue', $sp))
                ->fetchAll(\PDO::FETCH_ASSOC);

            // find product channels
            $productChannels = $this->getProductsChannels(array_column($pavData, 'productId'));

            // filtering products
            $productsIds = [];
            foreach ($pavData as $v) {
                if ($v['scope'] == 'Channel' && (!isset($productChannels[$v['productId']]) || !in_array($v['channelId'], $productChannels[$v['productId']]))) {
                    continue 1;
                }
                $productsIds[] = $v['productId'];
            }

            if ($row['type'] === 'arrayNoneOf') {
                $selectParams['customWhere'] .= " AND product.id NOT IN ('" . implode("','", $productsIds) . "')";
                return;
            }

            // prepare custom where
            $selectParams['customWhere'] .= " AND product.id IN ('" . implode("','", $productsIds) . "')";
        }
    }

    protected function filteringByCategories(array &$params): void
    {
        foreach ($params['where'] as $k => $row) {
            if ($row['attribute'] == 'categories') {
                if (!empty($row['value'])) {
                    $categories = [];
                    foreach ($row['value'] as $id) {
                        $dbData = $this->fetchAll("SELECT id FROM category WHERE (id='$id' OR category_route LIKE '%|$id|%') AND deleted=0");
                        $categories = array_merge($categories, array_column($dbData, 'id'));
                    }
                    $innerSql = "SELECT product_id FROM product_category WHERE deleted=0 AND category_id IN ('" . implode("','", $categories) . "')";
                }

                switch ($row['type']) {
                    case 'linkedWith':
                        if (!empty($innerSql)) {
                            $this->customWhere .= " AND product.id IN ($innerSql) ";
                        }
                        break;
                    case 'isNotLinked':
                        $this->customWhere .= " AND product.id NOT IN (SELECT product_id FROM product_category WHERE deleted=0) ";
                        break;
                    case 'isLinked':
                        $this->customWhere .= " AND product.id IN (SELECT product_id FROM product_category WHERE deleted=0) ";
                        break;
                    case 'notLinkedWith':
                        if (!empty($innerSql)) {
                            $this->customWhere .= " AND product.id NOT IN ($innerSql) ";
                        }
                        break;
                }
                unset($params['where'][$k]);
            }
        }

        $params['where'] = array_values($params['where']);
    }

    /**
     * @param array $productsIds
     *
     * @return array
     */
    protected function getProductsChannels(array $productsIds): array
    {
        $productsChannels = $this
            ->getEntityManager()
            ->getPDO()
            ->query("SELECT product_id, channel_id FROM product_channel WHERE deleted=0 AND product_id IN ('" . implode("','", $productsIds) . "')")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $products = [];
        foreach ($productsChannels as $row) {
            $products[$row['product_id']][] = $row['channel_id'];
        }

        return $products;
    }

    protected function accessPortalOnlyAccount(&$result)
    {
        $accountId = $this->getUser()->get('accountId');

        if (!empty($accountId)) {
            $d['id'] = $this->getEntityManager()->getRepository('Product')->getProductsIdsViaAccountId($accountId);
        }

        if ($this->getSeed()->hasAttribute('createdById')) {
            $d['createdById'] = $this->getUser()->id;
        }

        if (!empty($d)) {
            $result['whereClause'][] = ['OR' => $d];
        } else {
            $result['whereClause'][] = ['id' => null];
        }
    }
}
