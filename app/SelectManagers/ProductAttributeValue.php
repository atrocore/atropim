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

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Espo\Core\Utils\Util;

class ProductAttributeValue extends AbstractSelectManager
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        if (isset($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $k => $v) {
                if ($v['value'] === 'onlyTabAttributes' && isset($v['data']['onlyTabAttributes'])) {
                    $onlyTabAttributes = true;
                    $tabId = $v['data']['onlyTabAttributes'];
                    if (empty($tabId) || $tabId === 'null') {
                        $tabId = null;
                    }
                    unset($params['where'][$k]);
                }
            }
            $params['where'] = array_values($params['where']);
        }

        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }

        $language = \Pim\Services\ProductAttributeValue::getHeader('language');
        if (!empty($language)) {
            if (!$this->getConfig()->get('isMultilangActive') || !in_array($language, $this->getConfig()->get('inputLanguageList', []))) {
                throw new BadRequest('No such language is available.');
            }
            $selectParams['customWhere'] .= " AND product_attribute_value.language IN ('main','$language')";
        }

        if (!empty($onlyTabAttributes)) {
            if (empty($tabId)) {
                $selectParams['customWhere'] .= " AND product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id IS NULL AND deleted=0)";
            } else {
                $tabId = $this->getEntityManager()->getPDO()->quote($tabId);
                $selectParams['customWhere'] .= " AND product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id=$tabId AND deleted=0)";
            }
        }

        return $selectParams;
    }

    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        if ($this->isSubQuery) {
            return;
        }

        $additionalSelectColumns = [
            'typeValue'          => 'attribute.type_value',
            'attributeGroupId'   => 'ag1.id',
            'attributeGroupName' => 'ag1.name'
        ];

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $lcLanguage = strtolower($language);
                $camelCaseLanguage = ucfirst(Util::toCamelCase($lcLanguage));

                $additionalSelectColumns["typeValue$camelCaseLanguage"] = "attribute.type_value_$lcLanguage";
            }
        }

        $result['customJoin'] .= " LEFT JOIN attribute_group AS ag1 ON ag1.id=attribute.attribute_group_id AND ag1.deleted=0";

        foreach ($additionalSelectColumns as $alias => $sql) {
            $result['additionalSelectColumns'][$sql] = $alias;
        }
    }

    /**
     * @inheritDoc
     */
    protected function accessOnlyOwn(&$result)
    {
        $d['createdById'] = $this->getUser()->id;
        $d['ownerUserId'] = $this->getUser()->id;
        $d['assignedUserId'] = $this->getUser()->id;

        $result['whereClause'][] = array(
            'OR' => $d
        );
    }

    /**
     * @param array $result
     */
    protected function boolFilterLinkedWithAttributeGroup(array &$result)
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
