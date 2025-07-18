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

use Atro\Core\Exceptions\BadRequest;
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

    protected function boolFilterNotForbiddenForEditFields(array &$result): void
    {
        if ($this->getUser()->isAdmin()) {
            return;
        }

        $entityName = $this->getSelectCondition('notForbiddenForEditFields')['entityName'] ?? null;
        $entityId = $this->getSelectCondition('notForbiddenForEditFields')['entityId'] ?? null;

        if (empty($entityName) || empty($entityId)) {
            return;
        }

        $forbiddenFieldsList = $this->getAcl()->getScopeForbiddenAttributeList($entityName, 'edit');

        foreach ($this->getUser()->get('roles') ?? [] as $role) {
            $aclData = $this->getEntityManager()->getRepository('Role')->getAclData($role);
            foreach ($aclData->attributes->$entityName ?? [] as $field => $attributeId) {
                if (in_array($field, $forbiddenFieldsList)) {
                    $result['whereClause'][] = [
                        'id!=' => $attributeId
                    ];
                }
            }
        }
    }

    protected function boolFilterNotLinkedWithCurrent(array &$result): void
    {
        $attributeId = (string)$this->getSelectCondition('notLinkedWithCurrent');

        if(empty($attributeId)) {
            return;
        }

        $result['whereClause'][] = [
            'OR' => [
                'compositeAttributeId!=' => $attributeId,
                'compositeAttributeId=' => null
            ]
        ];
    }

    protected function boolFilterOnlyCompositeAttributes(array &$result): void
    {
        $attributeId = (string)$this->getSelectCondition('onlyCompositeAttributes');

        $result['whereClause'][] = [
            'type=' => 'composite'
        ];

        if(!empty($attributeId)) {
            $result['whereClause'][] = [
                'id!=' => $attributeId,
            ];
        }
    }

    protected function boolFilterNotParentCompositeAttribute(array &$result): void
    {
        $attributeId = (string)$this->getSelectCondition('notParentCompositeAttribute');
        if (empty($attributeId)) {
            return;
        }

        $ids = [$attributeId];
        $this->getEntityManager()->getRepository('Attribute')->prepareAllParentsCompositeAttributesIds($attributeId, $ids);

        $result['whereClause'][] = [
            'id!=' => $ids
        ];
    }

    protected function boolFilterNotChildCompositeAttribute(array &$result): void
    {
        $attributeId = (string)$this->getSelectCondition('notChildCompositeAttribute');
        if (empty($attributeId)) {
            return;
        }

        $ids = [$attributeId];
        $this->getEntityManager()->getRepository('Attribute')->prepareAllChildrenCompositeAttributesIds($attributeId, $ids);

        $result['whereClause'][] = [
            'id!=' => $ids
        ];
    }

    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getSelectCondition('onlyForEntity');
        if (!empty($entityName)) {
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }

    protected function boolFilterOnlyForAttributePanel(array &$result): void
    {
        $attributePanelId = (string)$this->getSelectCondition('onlyForAttributePanel');
        if (!empty($attributePanelId)) {
            $result['whereClause'][] = [
                'attributePanelId' => $attributePanelId
            ];
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
