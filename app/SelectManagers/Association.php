<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
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
        $connection = $this->getEntityManager()->getConnection();

        $qb = $connection->createQueryBuilder()
            ->select('association_id')
            ->from('associated_product')
            ->where('main_product_id = :mainProductId')
            ->andWhere('deleted = :false')
            ->setParameter('mainProductId', $mainProductId, Mapper::getParameterType($mainProductId))
            ->setParameter('false', false, Mapper::getParameterType(false));

        if (!empty($relatedProductId)) {
            $qb->andWhere('related_product_id = :relatedProductId');
            $qb->setParameter('relatedProductId', $relatedProductId, Mapper::getParameterType($relatedProductId));
        }

        return $qb->fetchAllAssociative();
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
