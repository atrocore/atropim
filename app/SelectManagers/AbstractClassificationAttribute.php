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

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class AbstractClassificationAttribute extends AbstractSelectManager
{
    public function filterByAttributeType(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper)
    {
        $connection = $this->getEntityManager()->getConnection();

        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $attributeTypes = array_keys($this->getMetadata()->get('attributes'));

        $qb->andWhere("{$tableAlias}.attribute_id IN (SELECT a.id FROM {$connection->quoteIdentifier('attribute')} a WHERE a.type IN (:attributeTypes) AND deleted=:false)");
        $qb->setParameter('attributeTypes', $attributeTypes, Mapper::getParameterType($attributeTypes));
        $qb->setParameter('false', false, Mapper::getParameterType(false));
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'filterByAttributeType'];
    }

    /**
     * @param array $result
     */
    protected function boolFilterLinkedWithAttributeGroup(array &$result)
    {
        $data = (array)$this->getSelectCondition('linkedWithAttributeGroup');

        if (isset($data['classificationId'])) {
            // prepare data
            $ids = [$data['classificationId']];
            $attributeGroupId = ($data['attributeGroupId'] != '') ? $data['attributeGroupId'] : null;

            $result['whereClause'][] = [
                'id' => $this->getEntityManager()->getRepository('Classification')->getLinkedWithAttributeGroup($ids, $attributeGroupId)
            ];
        }
    }
}