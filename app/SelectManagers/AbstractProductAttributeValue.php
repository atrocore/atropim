<?php

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class AbstractProductAttributeValue extends AbstractSelectManager
{
    protected function hasIncludeUniLingualValuesBoolFilter(array $params): bool
    {
        foreach ($params['where'] ?? [] as $row) {
            if ($row['type'] == 'bool'
                && !empty($row['value'])
                && is_array($row['value'])
                && in_array('includeUniLingualValues', $row['value'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
-        $includeUniLingualValues = $this->hasIncludeUniLingualValuesBoolFilter($params);
        $connection = $this->getEntityManager()->getConnection();
        $uniLangItem = [
            'type'      => 'in',
            'attribute' => 'attributeId',
            'value'     => [
                'innerSql' => [
                    "sql"        => "SELECT attr1.id FROM {$connection->quoteIdentifier('attribute')} attr1 WHERE attr1.deleted=:false AND is_multilang=" .
                        ($includeUniLingualValues ? ':false' : ':true'),
                    "parameters" => $connection->createQueryBuilder()
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('true', true, ParameterType::BOOLEAN)->getParameters()
                ]
            ]
        ];
        $hasLanguageFilter = false;

        if (isset($params['where']) && is_array($params['where'])) {
            $pushBoolAttributeType = false;
            foreach ($params['where'] as $k => $v) {
                if ($v['value'] === 'onlyTabAttributes' && isset($v['data']['onlyTabAttributes'])) {
                    $this->onlyTabAttributes = true;
                    $this->tabId = $v['data']['onlyTabAttributes'];
                    if (empty($this->tabId) || $this->tabId === 'null') {
                        $this->tabId = null;
                    }
                    unset($params['where'][$k]);
                }
                if (!empty($v['attribute']) && $v['attribute'] === 'boolValue') {
                    $pushBoolAttributeType = true;
                }
                if (!empty($v['attribute']) && $v['attribute'] === 'language') {
                    $params['where'][$k] = [
                        'type'  => $includeUniLingualValues ? 'or' : 'and',
                        'value' => [
                            $v,
                            $uniLangItem
                        ]
                    ];

                    $hasLanguageFilter = true;
                }
            }
            $params['where'] = array_values($params['where']);

            if ($pushBoolAttributeType) {
                $params['where'][] = [
                    "type"      => "equals",
                    "attribute" => "attributeType",
                    "value"     => "bool"
                ];
            }


        }

        if (empty($hasLanguageFilter) && $includeUniLingualValues) {
            $params['where'][] = $uniLangItem;
        }

        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        return $selectParams;
    }

    public function selectAttributeGroup(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (!empty($params['aggregation']) || $this->isSubQuery) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->leftJoin($tableAlias, $connection->quoteIdentifier('attribute'), 'a1', "a1.id={$tableAlias}.attribute_id AND a1.deleted=:false");
        $qb->leftJoin($tableAlias, $connection->quoteIdentifier('attribute_group'), 'ag1', "ag1.id=a1.attribute_group_id AND ag1.deleted=:false");
        $qb->setParameter('false', false, Mapper::getParameterType(false));

        $qb->add('select', ["ag1.id as {$mapper->getQueryConverter()->fieldToAlias('attributeGroupId')}"], true);
        $qb->add('select', ["ag1.name as {$mapper->getQueryConverter()->fieldToAlias('attributeGroupName')}"], true);
    }

    public function filterByLanguage(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $language = \Pim\Services\ProductAttributeValue::getLanguagePrism();

        $languages = ['main'];
        if ($this->getConfig()->get('isMultilangActive')) {
            $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
        }

        if (!empty($language) && !in_array($language, $languages)) {
            throw new BadRequest('No such language is available.');
        }

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->andWhere("$tableAlias.language IN (:languages)");
        $qb->setParameter('languages', $languages, Mapper::getParameterType($languages));
    }

    public function filterByTab(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (empty($this->onlyTabAttributes)) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        if (empty($this->tabId)) {
            $qb->andWhere("$tableAlias.attribute_id IN (SELECT attr.id FROM {$connection->quoteIdentifier('attribute')} attr WHERE attr.attribute_tab_id IS NULL AND attr.deleted=:false)");
            $qb->setParameter('false', false, Mapper::getParameterType(false));
        } else {
            $qb->andWhere("$tableAlias.attribute_id IN (SELECT attr.id FROM {$connection->quoteIdentifier('attribute')} attr WHERE attr.attribute_tab_id=:tabId AND deleted=:false)");
            $qb->setParameter('tabId', $this->tabId, Mapper::getParameterType($this->tabId));
            $qb->setParameter('false', false, Mapper::getParameterType(false));
        }
    }

    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        $result['callbacks'][] = [$this, 'selectAttributeGroup'];
        $result['callbacks'][] = [$this, 'filterByLanguage'];
        $result['callbacks'][] = [$this, 'filterByTab'];
    }

    protected function boolFilterLinkedWithAttributeGroup(array &$result): void
    {
        $data = (array)$this->getSelectCondition('linkedWithAttributeGroup');

        if (isset($data['productId'])) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id'])
                ->distinct()
                ->join('attribute')
                ->where(
                    [
                        'productId'                  => $data['productId'],
                        'attribute.attributeGroupId' => ($data['attributeGroupId'] != '') ? $data['attributeGroupId'] : null
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id' => array_column($attributes, 'id')
            ];
        }
    }
}