<?php

declare(strict_types=1);

namespace Pim\Services;

use Espo\ORM\Entity;

/**
 * Brand service
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Brand extends AbstractService
{
    /**
     * @param Entity $entity
     */
    protected function afterDeleteEntity(Entity $entity)
    {
        // call parent action
        parent::afterDeleteEntity($entity);

        // unlink
        $this->unlinkBrand([$entity->get('id')]);
    }

    /**
     * @param array $idList
     */
    protected function afterMassRemove(array $idList)
    {
        // call parent action
        parent::afterMassRemove($idList);

        // unlink
        $this->unlinkBrand($idList);
    }

    /**
     * Unlink brand from products
     *
     * @param array $ids
     *
     * @return bool
     */
    protected function unlinkBrand(array $ids): bool
    {
        // prepare data
        $result = false;

        if (!empty($ids)) {
            // prepare ids
            $ids = implode("','", $ids);

            // prepare sql
            $sql = sprintf("UPDATE product SET brand_id = null WHERE brand_id IN ('%s');", $ids);

            $sth = $this
                ->getEntityManager()
                ->getPDO()
                ->prepare($sql);
            $sth->execute();

            // prepare result
            $result = true;
        }

        return $result;
    }
}
