<?php

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of Association
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
