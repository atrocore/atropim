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

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

/**
 * Brand service
 */
class Brand extends Base
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
