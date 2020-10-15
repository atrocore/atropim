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
 * Class of Association
 */
class Association extends AbstractSelectManager
{
    /**
     * Get associated products associations
     *
     * @param string $mainProductId
     * @param string $relatedProductId
     *
     * @return array
     */
    public function getAssociatedProductAssociations($mainProductId, $relatedProductId)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql = 'SELECT
          association_id 
        FROM
          associated_product
        WHERE
          main_product_id =' . $pdo->quote($mainProductId) . '
          AND related_product_id = ' . $pdo->quote($relatedProductId) . '
          AND deleted = 0';
        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * NotUsedAssociations filter
     *
     * @param array $result
     */
    protected function boolFilterNotUsedAssociations(&$result)
    {
        // prepare data
        $data = (array)$this->getSelectCondition('notUsedAssociations');

        if (!empty($data['relatedProductId'])) {
            $assiciations = $this
                ->getAssociatedProductAssociations($data['mainProductId'], $data['relatedProductId']);
            foreach ($assiciations as $row) {
                $result['whereClause'][] = [
                    'id!=' => (string)$row['association_id']
                ];
            }
        }
    }
}
