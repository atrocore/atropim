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

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Pim\Services\GeneralStatisticsDashlet;
use Pim\Entities\Attribute;

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
        // filtering by categories
        $this->filteringByCategories($params);

        if (!empty($params['where']) && is_array($params['where'])) {
            $where = [];
            foreach ($params['where'] as $row) {
                if (!empty($row['isAttribute']) || !empty($row['value'][0]['isAttribute'])) {
                    $productAttributes[] = $row;
                } elseif (!empty($row['attribute']) && $row['attribute'] === 'modifiedAtExpanded') {
                    $productIds = [];
                    foreach (['ProductAsset', 'ProductAttributeValue', 'ProductChannel'] as $entityType) {
                        $sp = $this->createSelectManager($entityType)->getSelectParams(['where' => [array_merge($row, ['attribute' => 'modifiedAt'])]], true, true);
                        $sp['select'] = ['productId'];
                        $collection = $this->getEntityManager()->getRepository($entityType)->find($sp);
                        $productIds = array_column($collection->toArray(), 'productId');
                    }

                    $where[] = [
                        'type'  => 'or',
                        'value' => [
                            array_merge($row, ['attribute' => 'modifiedAt']),
                            [
                                'type'      => 'in',
                                'attribute' => 'id',
                                'value'     => array_values(array_unique($productIds))
                            ]
                        ]
                    ];
                } else {
                    $where[] = $row;
                }
            }

            $params['where'] = $where;
        }

        // get select params
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        // prepare custom where
        $selectParams['customWhere'] .= $this->customWhere;

        // add product attributes filter
        if (!empty($productAttributes)) {
            $this->addProductAttributesFilter($selectParams, $productAttributes);
        }

        // for products in category page
        if (!empty($params['sortBy']) && $params['sortBy'] == 'sorting') {
            $selectParams['additionalColumns']['sorting'] = 'sorting';
            $selectParams['orderBy'] = 'product_category_mm.sorting';
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
                'attributeType' => ['varchar', 'text', 'wysiwyg', 'extensibleEnum'],
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
        return $this->fetchAll($this->getGeneralStatisticService()->getQueryProductWithoutAssets());
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
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('related_product_id')
            ->from('associated_product')
            ->where('main_product_id = :productId')
            ->andWhere('association_id = :associationId')
            ->andWhere('deleted = :false')
            ->setParameter('productId', $productId, Mapper::getParameterType($productId))
            ->setParameter('associationId', $associationId, Mapper::getParameterType($associationId))
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();
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

    protected function getProductsIdsByClassificationIds(array $classificationIds): array
    {
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->join('classifications')
            ->select(['id'])
            ->where(['classifications.id' => $classificationIds])
            ->find();

        return array_column($products->toArray(), 'id');
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

    protected function boolFilterLinkedWithClassification(array &$result)
    {
        if (empty($id = $this->getSelectCondition('linkedWithClassification'))) {
            return;
        }

        /** @var \Pim\Repositories\Classification $repository */
        $repository = $this->getEntityManager()->getRepository('Classification');
        if (empty($classification = $repository->get($id))) {
            throw new BadRequest('No such Classification');
        }

        $ids = $repository->getChildrenRecursivelyArray($classification->get('id'));
        $ids[] = $classification->get('id');

        $result['whereClause'][] = [
            'id' => $this->getProductsIdsByClassificationIds($ids)
        ];
    }

    protected function boolFilterWithoutMainImage(&$result)
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('p.id')
            ->from($connection->quoteIdentifier('product'), 'p')
            ->where('p.id NOT IN (SELECT DISTINCT pa.product_id FROM product_asset pa WHERE pa.is_main_image = :true)')
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->fetchAllAssociative();

        $result['whereClause'][] = [
            'id' => array_column($res, 'id')
        ];
    }

    protected function getAttribute(string $id): Attribute
    {
        if (!isset($this->attributes[$id])) {
            if (substr($id, -6) === 'UnitId') {
                $id = substr($id, 0, -6);
            } elseif (substr($id, -4) === 'From') {
                $id = substr($id, 0, -4);
            } elseif (substr($id, -2) === 'Id') {
                $id = substr($id, 0, -2);
            } elseif (substr($id, -2) === 'To') {
                $id = substr($id, 0, -2);
            }

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

        if (!isset($row['type'])) {
            $row['type'] = in_array($attribute->get('type'), ['array', 'extensibleMultiEnum']) ? 'arrayIsEmpty' : 'isNull';
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
            case 'extensibleMultiEnum':
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
            case 'int':
            case 'rangeInt':
                if (substr($row['attribute'], -6) === 'UnitId') {
                    if ($row['type'] === 'isNull') {
                        $row = [
                            'type'  => 'or',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'varcharValue',
                                    'value'     => ''
                                ],
                                [
                                    'type'      => 'isNull',
                                    'attribute' => 'varcharValue'
                                ],
                            ]
                        ];
                    } else {
                        $row['attribute'] = 'varcharValue';
                    }
                } elseif (substr($row['attribute'], -2) === 'To') {
                    $row['attribute'] = 'intValue1';
                } else {
                    $row['attribute'] = 'intValue';
                }
                $where['value'][] = $row;
                break;
            case 'currency':
            case 'float':
            case 'rangeFloat':
                if (substr($row['attribute'], -6) === 'UnitId') {
                    if ($row['type'] === 'isNull') {
                        $row = [
                            'type'  => 'or',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'varcharValue',
                                    'value'     => ''
                                ],
                                [
                                    'type'      => 'isNull',
                                    'attribute' => 'varcharValue'
                                ],
                            ]
                        ];
                    } else {
                        $row['attribute'] = 'varcharValue';
                    }
                } elseif (substr($row['attribute'], -2) === 'To') {
                    $row['attribute'] = 'floatValue1';
                } else {
                    $row['attribute'] = 'floatValue';
                }
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
            case 'extensibleEnum':
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

        if ($attribute->get('type') === 'extensibleMultiEnum') {
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
            $pavData = $this->getEntityManager()->getRepository('ProductAttributeValue')->find($sp)->toArray();

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
        if (empty($params['where'])) {
            return;
        }

        foreach ($params['where'] as $k => $row) {
            if (empty($row['attribute'])) {
                continue;
            }
            if ($row['attribute'] == 'categories' && empty($row['subQuery'])) {
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
        $connection = $this->getEntityManager()->getConnection();

        $productsChannels = $connection->createQueryBuilder()
            ->select('t.product_id, t.channel_id')
            ->from($connection->quoteIdentifier('product_channel'), 't')
            ->where('t.deleted = :false')
            ->andWhere('t.product_id IN (:ids)')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('ids', $productsIds, Mapper::getParameterType($productsIds))
            ->fetchAllAssociative();

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
