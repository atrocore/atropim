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

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Espo\Core\Utils\Util;

class ProductAttributeValue extends AbstractSelectManager
{
    protected array $filterLanguages = [];
    protected array $filterScopes = [];

    public static function createLanguagePrismBoolFilterName(string $language): string
    {
        return 'prismVia' . ucfirst(Util::toCamelCase(strtolower($language)));
    }

    public static function createScopePrismBoolFilterName(string $id): string
    {
        return 'prismViaScope_' . $id;
    }

    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        // clear filter languages
        $this->filterLanguages = [];

        if (isset($params['where']) && is_array($params['where'])) {
            $pushBoolAttributeType = false;
            foreach ($params['where'] as $k => $v) {
                if ($v['value'] === 'onlyTabAttributes' && isset($v['data']['onlyTabAttributes'])) {
                    $onlyTabAttributes = true;
                    $tabId = $v['data']['onlyTabAttributes'];
                    if (empty($tabId) || $tabId === 'null') {
                        $tabId = null;
                    }
                    unset($params['where'][$k]);
                }
                if (!empty($v['attribute']) && $v['attribute'] === 'boolValue') {
                    $pushBoolAttributeType = true;
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

        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }

        $language = \Pim\Services\ProductAttributeValue::getLanguagePrism();
        if (!empty($language)) {
            $languages = ['main'];
            if ($this->getConfig()->get('isMultilangActive')) {
                $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
            }
            if (!in_array($language, $languages)) {
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

        $this->applyLanguageBoolFilters($params, $selectParams);
        $this->applyScopeBoolFilters($params, $selectParams);

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
            'attributeGroupId'   => 'ag1.id',
            'attributeGroupName' => 'ag1.name'
        ];

        $result['customJoin'] .= " LEFT JOIN attribute AS a1 ON a1.id=product_attribute_value.attribute_id AND a1.deleted=0";
        $result['customJoin'] .= " LEFT JOIN attribute_group AS ag1 ON ag1.id=a1.attribute_group_id AND ag1.deleted=0";

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

    public function applyBoolFilter($filterName, &$result)
    {
        if (self::createLanguagePrismBoolFilterName('main') === $filterName) {
            $this->filterLanguages[] = 'main';
        }
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                if (self::createLanguagePrismBoolFilterName($language) === $filterName) {
                    $this->filterLanguages[] = $language;
                }
            }
        }

        if (self::createScopePrismBoolFilterName('global') === $filterName) {
            $this->filterScopes[] = 'global';
        }

        foreach ($this->getMetadata()->get(['clientDefs', 'ProductAttributeValue', 'channels'], []) as $channel) {
            if (self::createScopePrismBoolFilterName($channel['id']) === $filterName) {
                $this->filterScopes[] = $channel['id'];
            }
        }

        parent::applyBoolFilter($filterName, $result);
    }

    public function applyLanguageBoolFilters($params, &$selectParams)
    {
        if (empty($this->filterLanguages)) {
            return;
        }

        $languages = implode("','", $this->filterLanguages);

        $selectParams['customWhere'] .= " AND (product_attribute_value.language IN ('$languages') OR product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE deleted=0 AND is_multilang=0))";
    }

    public function applyScopeBoolFilters($params, &$selectParams)
    {
        if (empty($this->filterScopes)) {
            return;
        }

        $channelsIds = [];
        foreach ($this->filterScopes as $channelId) {
            if ($channelId !== 'global') {
                $channelsIds[] = $channelId;
            }
        }
        $channelsIds[] = '';

        $subQuery = "SELECT id FROM product_attribute_value WHERE channel_id IN ('" . implode("','", $channelsIds) . "') AND deleted=0";

        $selectParams['customWhere'] .= " AND product_attribute_value.id IN ($subQuery)";
    }
}
