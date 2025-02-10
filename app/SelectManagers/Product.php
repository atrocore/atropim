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

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Pim\Entities\Attribute;

class Product extends AbstractSelectManager
{
    private array $attributes = [];

    private array $filterByCategories = [];

    private string $textFilter = '';

    private array $textFilterParams = [];

    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $this->prepareFilterByCategories($params);

        if (!empty($params['where']) && is_array($params['where'])) {
            $this->mutateWhereQuery($params['where']);
            $this->mutateWhereAttributeQuery($params['where']);

            $where = [];
            foreach ($params['where'] as $k => $row) {
                if (!empty($row['attribute']) && $row['attribute'] === 'modifiedAtExpanded') {
                    $where[$k] = $this->prepareWhereForModifiedAtExpanded($row);
                } else if (!empty($row['type']) && $row['type'] == 'textFilter') {
                    $this->textFilterParams[] = $row;
                } else {
                    $where[] = $row;
                }
            }

            $params['where'] = $where;
        }

        // get select params
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        $selectParams['callbacks'][] = [$this, 'applyFilterText'];

        // add filtering by categories
        $selectParams['callbacks'][] = [$this, 'applyFilteringByCategories'];

        // for products in category page
        if (!empty($params['sortBy']) && $params['sortBy'] == 'sorting') {
            $selectParams['additionalColumns']['sorting'] = 'sorting';
            $selectParams['orderBy'] = 'product_category_mm.sorting';
        }

        return $selectParams;
    }

    public function prepareWhereForModifiedAtExpanded(array $row): array
    {
        $result = [
            'type'  => 'innerSql',
            'value' => [
                "sql"        => "",
                "parameters" => []
            ]
        ];

        $entitiesTypeList = ['ProductFile', 'ProductAttributeValue', 'ProductChannel'];
        foreach ($entitiesTypeList as $key => $entityType) {
            $sp = $this->createSelectManager($entityType)
                ->getSelectParams(['where' => [array_merge($row, ['attribute' => 'modifiedAt'])]], true, true);
            $sp['select'] = ['productId'];

            $repository = $this->getEntityManager()->getRepository($entityType);

            $qb1 = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp, true);

            $mainTableAlias = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

            $innerSql = str_replace($mainTableAlias, "t_modified_at", $qb1->getSql());

            $result['value']['sql'] .= $innerSql;
            if ($key != count($entitiesTypeList) - 1) {
                $result['value']['sql'] .= " UNION ";
            }

            $result['value']['parameters'] = array_merge($result['value']['parameters'], $qb1->getParameters());
        }

        $result['value']['sql'] = "$mainTableAlias.id IN ({$result['value']['sql']})";

        return $result;
    }

    public function mutateWhereAttributeQuery(array &$where): void
    {
        foreach ($where as &$item) {
            if (isset($item['value']) && is_array($item['value']) && empty($item['isAttribute'])) {
                $this->mutateWhereAttributeQuery($item['value']);
            } else {
                if (!empty($item['isAttribute'])) {
                    /** @var \Pim\Repositories\ProductAttributeValue $pavRepo */
                    $pavRepo = $this->getEntityManager()->getRepository('ProductAttributeValue');

                    $attributeId = $item['attribute'];

                    $sp = $this->createSelectManager('ProductAttributeValue')->getSelectParams(['where' => [$this->convertAttributeWhere($item)]], true, true);
                    $sp['select'] = ['productId'];

                    $qb1 = $pavRepo->getMapper()->createSelectQueryBuilder($pavRepo->get(), $sp);

                    $operator = 'IN';
                    if (isset($item['type']) && $item['type'] === 'arrayNoneOf') {
                        $operator = 'NOT IN';
                    }

                    $mainTableAlias = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

                    $innerSql = str_replace($mainTableAlias, "t_{$attributeId}", $qb1->getSql());

                    $item = [
                        'type'  => 'innerSql',
                        'value' => [
                            "sql"        => "$mainTableAlias.id $operator ({$innerSql})",
                            "parameters" => $qb1->getParameters()
                        ]
                    ];
                }
            }
        }
    }

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

        if (!empty($textFilter)) {
            $this->textFilter = $textFilter;
        }

        $result['whereClause'][] = $last;
    }

    protected function boolFilterWithoutProductAttributes(&$result)
    {
        $result['callbacks'][] = [$this, 'applyBoolFilterWithoutProductAttributes'];
    }

    public function applyBoolFilterWithoutProductAttributes(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->andWhere("$tableAlias.id NOT IN (SELECT DISTINCT product_id FROM product_attribute_value WHERE deleted=:false)");
        $qb->setParameter('false', false, Mapper::getParameterType(false));
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
     * NotLinkedWithBrand filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithBrand(array &$result)
    {
        // prepare data
        $brandId = (string)$this->getSelectCondition('notLinkedWithBrand');

        if (!empty($brandId)) {
            $products = $this->getEntityManager()->getRepository('Product')
                ->select(['id'])
                ->where(['brandId' => $brandId])
                ->find();

            $result['whereClause'][] = [
                'id!=' => array_column($products->toArray(), 'id')
            ];
        }
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

    protected function fetchAll(string $query): array
    {
        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        if (str_contains($query, ':zero')) {
            $sth->bindValue(':zero', 0, \PDO::PARAM_INT);
        }
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
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

    protected function boolFilterLinkedWithCategory(array &$result)
    {
        $result['callbacks'][] = [$this, 'applyBoolFilterLinkedWithCategory'];
    }

    public function applyBoolFilterLinkedWithCategory(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $id = $this->getSelectCondition('linkedWithCategory');
        if (empty($id)) {
            return;
        }

        $repository = $category = $this->getEntityManager()->getRepository('Category');
        if (empty($category = $repository->get($id))) {
            throw new BadRequest('No such category');
        }

        // collect categories
        $categoriesIds = $repository->getChildrenRecursivelyArray($category->get('id'));
        $categoriesIds[] = $category->get('id');

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("{$tableAlias}.id IN (SELECT product_id FROM product_category WHERE product_id IS NOT NULL AND deleted=:false AND category_id IN (:categoriesIds))");
        $qb->setParameter('false', false, Mapper::getParameterType(false));
        $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
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
            ->where('p.id NOT IN (SELECT DISTINCT pa.product_id FROM product_file pa WHERE pa.is_main_image = :true)')
            ->setParameter('true', true, ParameterType::BOOLEAN)
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
        if (isset($row['type']) && in_array($row['type'], ['or', 'and']) && !empty($row['value'])) {
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
                        // escape slashes to search in escaped json
                        $value = str_replace('\\', '\\\\\\\\', $value);
                        $value = str_replace("/", "\\\\/", $value);
                        $where['value'][1]['value'][] = [
                            'type'      => 'like',
                            'attribute' => 'textValue',
                            'value'     => "%\"$value\"%"
                        ];
                    }
                }
                break;
            case 'text':
            case 'markdown':
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
                                    'attribute' => 'referenceValue',
                                    'value'     => ''
                                ],
                                [
                                    'type'      => 'isNull',
                                    'attribute' => 'referenceValue'
                                ],
                            ]
                        ];
                    } else {
                        $row['attribute'] = 'referenceValue';
                    }
                } elseif (substr($row['attribute'], -2) === 'To') {
                    $row['attribute'] = 'intValue1';
                } else {
                    $row['attribute'] = 'intValue';
                }
                $where['value'][] = $row;
                break;
            case 'float':
            case 'rangeFloat':
                if (substr($row['attribute'], -6) === 'UnitId') {
                    if ($row['type'] === 'isNull') {
                        $row = [
                            'type'  => 'or',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'referenceValue',
                                    'value'     => ''
                                ],
                                [
                                    'type'      => 'isNull',
                                    'attribute' => 'referenceValue'
                                ],
                            ]
                        ];
                    } else {
                        $row['attribute'] = 'referenceValue';
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
                $row['attribute'] = 'referenceValue';
                $where['value'][] = $row;
                $where['value'][] = [
                    'type'      => 'equals',
                    'attribute' => 'language',
                    'value'     => 'main',
                ];
                break;
            case 'file':
            case 'link':
                $row['attribute'] = 'referenceValue';
                $where['value'][] = $row;
                break;
            case 'linkMultiple':
                $row['attribute'] = $attribute->getLinkMultipleLinkName();
                $where['value'][] = $row;
                break;
            case 'varchar':
                if (substr($row['attribute'], -6) === 'UnitId') {
                    if ($row['type'] === 'isNull') {
                        $row = [
                            'type'  => 'or',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'referenceValue',
                                    'value'     => ''
                                ],
                                [
                                    'type'      => 'isNull',
                                    'attribute' => 'referenceValue'
                                ],
                            ]
                        ];
                    } else {
                        $row['attribute'] = 'referenceValue';
                    }
                } else {
                    $row['attribute'] = 'varcharValue';
                }
                $where['value'][] = $row;
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

    protected function prepareFilterByCategories(array &$params): void
    {
        if (empty($params['where'])) {
            return;
        }

        $this->filterByCategories = [];

        foreach ($params['where'] as $k => $row) {
            if (empty($row['attribute'])) {
                continue;
            }
            if ($row['attribute'] == 'categories' && empty($row['subQuery'])) {
                if (!empty($row['value'])) {
                    $this->filterByCategories['ids'] = array_merge($this->filterByCategories, $row['value']);
                    $this->filterByCategories['row'] = $row;
                    unset($params['where'][$k]);
                }
            }
        }

        $params['where'] = array_values($params['where']);
    }

    public function applyFilteringByCategories(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (empty($this->filterByCategories['ids'])) {
            return;
        }

        $ids = $this->filterByCategories['ids'];
        $row = $this->filterByCategories['row'];

        $connection = $this->getEntityManager()->getConnection();

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $categoriesIds = [];
        if (in_array($row['type'], ['linkedWith', 'notLinkedWith'])) {
            foreach ($ids as $id) {
                $res = $connection->createQueryBuilder()
                    ->select('c.id')
                    ->from($connection->quoteIdentifier('category'), 'c')
                    ->where('c.id= :id OR c.category_route LIKE :idLike')
                    ->andWhere('c.deleted = :false')
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->setParameter('id', $id)
                    ->setParameter('idLike', "%|{$id}|%")
                    ->fetchAllAssociative();
                $categoriesIds = array_merge($categoriesIds, array_column($res, 'id'));
            }
        }

        switch ($row['type']) {
            case 'isNotLinked':
                $qb->andWhere("$tableAlias.id NOT IN (SELECT pc44.product_id FROM product_category pc44 WHERE pc44.deleted=:false)");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                break;
            case 'isLinked':
                $qb->andWhere("$tableAlias.id IN (SELECT pc44.product_id FROM product_category pc44 WHERE pc44.deleted=:false)");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                break;
            case 'linkedWith':
                $qb->andWhere("$tableAlias.id IN (SELECT pc22.product_id FROM product_category pc22 WHERE pc22.deleted=:false AND pc22.category_id IN (:categoriesIds))");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
                break;
            case 'notLinkedWith':
                $qb->andWhere("$tableAlias.id NOT IN (SELECT pc22.product_id FROM product_category pc22 WHERE pc22.deleted=:false AND pc22.category_id IN (:categoriesIds))");
                $qb->setParameter('false', false, Mapper::getParameterType(false));
                $qb->setParameter('categoriesIds', $categoriesIds, Mapper::getParameterType($categoriesIds));
                break;
        }
    }

    public function applyFilterText(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $textFilterParams = [];
        foreach ($this->textFilterParams as $row) {
            if (isset($row['value']) || $row['value'] !== '') {
                $this->textFilter($row['value'], $textFilterParams);
            }
        }

        if (!empty($params['withDeleted'])) {
            $textFilterParams['withDeleted'] = true;
        }

        $textFilterQuery = $mapper->createSelectQueryBuilder($relEntity, $textFilterParams);

        if (empty($this->textFilter)) {
            return;
        }

        $textFilter = $this->textFilter;
        if (mb_strpos($textFilter, 'ft:') === 0) {
            $textFilter = mb_substr($textFilter, 3);
        }

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        /** @var \Pim\Repositories\ProductAttributeValue $pavRepo */
        $pavRepo = $this->getEntityManager()->getRepository('ProductAttributeValue');

        $where = [
            'type'  => 'and',
            'value' => [
                [
                    'type'      => 'in',
                    'attribute' => 'attributeType',
                    'value'     => ['varchar', 'text', 'wysiwyg', 'markdown', 'extensibleEnum']
                ],
                [
                    'type'  => 'or',
                    'value' => [
                        [
                            'type'      => 'like',
                            'attribute' => 'textValue',
                            'value'     => "$textFilter%"
                        ],
                        [
                            'type'      => 'like',
                            'attribute' => 'varcharValue',
                            'value'     => "$textFilter%"
                        ]
                    ]
                ]
            ]
        ];

        $sp = $this->createSelectManager('ProductAttributeValue')->getSelectParams(['where' => [$where]], true, true);
        $sp['select'] = ['productId'];

        if (!empty($params['withDeleted'])) {
            $sp['withDeleted'] = true;
        }

        $qb1 = $pavRepo->getMapper()->createSelectQueryBuilder($pavRepo->get(), $sp);
        $qb->andWhere(
            $qb->expr()->or(
                "{$tableAlias}.id IN ({$qb1->getSql()})",
                $textFilterQuery->getQueryPart('where')
            )
        );

        foreach (array_merge($qb1->getParameters(), $textFilterQuery->getParameters()) as $param => $val) {
            $qb->setParameter($param, $val, Mapper::getParameterType($val));
        }
    }
}
