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

namespace Pim\Services;

use Espo\ORM\Entity;

/**
 * Class AttributeGroup
 */
class AttributeGroup extends \Espo\Core\Templates\Services\Base
{
    /**
     * Get sorted linked attributes
     *
     * @param $attributeGroupId
     *
     * @return array
     */
    public function findLinkedEntitiesAttributes(string $attributeGroupId): array
    {
        $attributesTypes = array_keys($this->getMetadata()->get(['attributes'], []));

        $result = $this->getEntityManager()
            ->getRepository('Attribute')
            ->distinct()
            ->join('attributeGroup')
            ->where(['attributeGroupId' => $attributeGroupId, 'type' => $attributesTypes])
            ->order('sortOrderInAttributeGroup', 'ASC')
            ->find()
            ->toArray();

        return [
            'total' => count($result),
            'list'  => $result
        ];
    }

}
