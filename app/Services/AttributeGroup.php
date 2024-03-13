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

namespace Pim\Services;

use Atro\Core\Templates\Services\Base;

class AttributeGroup extends Base
{
    public function findLinkedEntitiesAttributes(string $attributeGroupId): array
    {
        $types = array_keys($this->getMetadata()->get(['attributes'], []));

        $collection = $this->getEntityManager()
            ->getRepository('Attribute')
            ->where([
                'attributeGroupId' => $attributeGroupId,
                'type'             => $types
            ])
            ->order('sortOrderInAttributeGroup', 'ASC')
            ->find();

        return [
            'total'      => count($collection),
            'collection' => $collection
        ];
    }
}
