<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of Attribute
 */
class Attribute extends AbstractSelectManager
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);
        $types = implode("','", array_keys($this->getMetadata()->get('attributes')));

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }
        // add filtering by attributes types
        $selectParams['customWhere'] .= " AND attribute.type IN ('{$types}')";

        return $selectParams;
    }

    /**
     * NotLinkedWithProduct filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProduct(&$result)
    {
        // prepare data
        $productId = (string)$this->getSelectCondition('notLinkedWithProduct');

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['attributeId'])
            ->where(['productId' => $productId])
            ->find();

        $result['whereClause'][] = [
            'id!=' => array_column($pavs->toArray(), 'attributeId')
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithClassificationAttribute(array &$result)
    {
        // get filter data
        $data = (array)$this->getSelectCondition('notLinkedWithClassificationAttribute');

        if (isset($data['classificationId']) && isset($data['channelsIds'])) {
            $attributesIds = $this
                ->getEntityManager()
                ->getRepository('ClassificationAttribute')
                ->select(['attributeId'])
                ->where(
                    [
                        'channelId' => $data['channelsIds'],
                        'classificationId' => $data['classificationId'],
                        'scope' => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($attributesIds, 'attributeId')
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProductAttributeValue(array &$result)
    {
        // get filter data
        $data = (array)$this->getSelectCondition('notLinkedWithProductAttributeValue');

        if (isset($data['productId']) && isset($data['channelId'])) {
            $attributesIds = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['attributeId'])
                ->where(
                    [
                        'channelId' => $data['channelId'],
                        'productId' => $data['productId'],
                        'scope'     => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($attributesIds, 'attributeId')
            ];
        }
    }

    protected function boolFilterOnlyDefaultChannelAttributes(array &$result)
    {
        $data = (array)$this->getSelectCondition('onlyDefaultChannelAttributes');

        if (isset($data['productId'])) {
            $availableChannels = $this
                ->getEntityManager()
                ->getRepository('ProductChannel')
                ->select(['channelId'])
                ->where(['productId' => $data['productId']])
                ->find()
                ->toArray();

            $excludedAttributes = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->select(['id'])
                ->where([
                    'defaultScope' => 'Channel',
                    'defaultChannelId!=' => array_column($availableChannels, 'channelId')
                ])
                ->find()
                ->toArray();

            if ($excludedAttributes) {
                $result['whereClause'][] = [
                    'id!=' => array_column($excludedAttributes, 'id')
                ];
            }
        }
    }

    protected function boolFilterFromAttributesTab(array &$result): void
    {
        $data = (array)$this->getSelectCondition('fromAttributesTab');

        if (isset($data['tabId'])) {
            $result['whereClause'][] = [
                'attributeTabId=' => empty($data['tabId']) ? null : $data['tabId']
            ];
        }
    }
}
