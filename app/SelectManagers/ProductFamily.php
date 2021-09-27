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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of ProductFamily
 */
class ProductFamily extends AbstractSelectManager
{
    protected function boolFilterNotParents(&$result): void
    {
        \Pim\Repositories\ProductFamily::onlyForAdvancedClassification();

        $repository = $this->getEntityManager()->getRepository('ProductFamily');
        $result['whereClause'][] = [
            'id!=' => $repository->getParentsIds($repository->get((string)$this->getSelectCondition('notParents')))
        ];
    }

    protected function boolFilterNotChildren(&$result): void
    {
        \Pim\Repositories\ProductFamily::onlyForAdvancedClassification();

        $repository = $this->getEntityManager()->getRepository('ProductFamily');
        $result['whereClause'][] = [
            'id!=' => $repository->getChildrenIds($repository->get((string)$this->getSelectCondition('notChildren')))
        ];
    }

    /**
     * NotLinkedWithAttribute filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithAttribute(&$result)
    {
        $productFamiliesIds = $this->getEntityManager()
            ->getRepository('ProductFamily')
            ->select(['id'])
            ->join(['attributes'])
            ->where([
                'attributes.Id' => (string)$this->getSelectCondition('notLinkedWithAttribute'),
            ])
            ->find()
            ->toArray();

        $result['whereClause'][] = [
            'id!=' => array_column($productFamiliesIds, 'id')
        ];
    }
}
