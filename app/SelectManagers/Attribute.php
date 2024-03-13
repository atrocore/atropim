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
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class Attribute extends AbstractSelectManager
{
    public function filterByType(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $attributeTypes = array_keys($this->getMetadata()->get('attributes'));

        $qb->andWhere("{$tableAlias}.type IN (:attributeTypes)");
        $qb->setParameter('attributeTypes', $attributeTypes, Mapper::getParameterType($attributeTypes));
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'filterByType'];
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
