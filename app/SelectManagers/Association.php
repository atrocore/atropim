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
    public function getAssociatedProductAssociations($mainProductId, $relatedProductId = null)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql = 'SELECT
          association_id 
        FROM
          associated_product
        WHERE
          main_product_id =' . $pdo->quote($mainProductId) . ' ' .
            (empty($relatedProductId) ? '' : ('AND related_product_id = ' . $pdo->quote($relatedProductId))) . '
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

    protected function boolFilterUsedAssociations(&$result)
    {
        // prepare data
        $data = (array)$this->getSelectCondition('usedAssociations');

        if (!empty($data['mainProductId'])) {
            $associations = $this
                ->getAssociatedProductAssociations($data['mainProductId']);
            $result['whereClause'][] = [
                'id' => array_map(function ($item) {
                    return (string)$item['association_id'];
                }, $associations)
            ];
        }
    }
}
