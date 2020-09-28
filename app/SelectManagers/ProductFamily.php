<?php

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of ProductFamily
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductFamily extends AbstractSelectManager
{

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
