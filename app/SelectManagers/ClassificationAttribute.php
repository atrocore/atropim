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

declare(strict_types=1);

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class ClassificationAttribute
 */
class ClassificationAttribute extends AbstractSelectManager
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);
        $types = array_keys($this->getMetadata()->get(['attributes'], []));
        $types = implode("','", $types);

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }

        // add filtering by attributes types
        $selectParams['customWhere'] .= " 
            AND classification_attribute.attribute_id IN (SELECT id 
                                                            FROM attribute 
                                                            WHERE type IN ('{$types}') AND deleted=0)";

        return $selectParams;
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
