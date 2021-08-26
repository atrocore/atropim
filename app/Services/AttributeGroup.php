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
        $attributesTypes = $this->getMetadata()->get('entityDefs.Attribute.fields.type.options', []);

        $result = $this->getEntityManager()
            ->getRepository('Attribute')
            ->distinct()
            ->join('attributeGroup')
            ->where(['attributeGroupId' => $attributeGroupId, 'type' => $attributesTypes])
            ->order('sortOrder', 'ASC')
            ->find()
            ->toArray();

        foreach ($result as $k => $v) {
            $result[$k]['sortOrder'] = $k * 10;
            $this->getEntityManager()->nativeQuery("UPDATE `attribute` SET sort_order={$result[$k]['sortOrder']} WHERE id='{$v['id']}'");
        }

        return [
            'total' => count($result),
            'list'  => $result
        ];
    }

    protected function duplicateAttributes(Entity $entity, Entity $duplicatingEntity): void
    {
        foreach ($duplicatingEntity->get('attributes') as $attribute) {
            $this->getRepository()->relate($entity, 'attributes', $attribute);
        }
    }
}
