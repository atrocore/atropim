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

namespace Pim\AclPortal;

use Espo\Core\AclPortal\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class Product extends Base
{
    public function checkInAccount(User $user, Entity $entity)
    {
        $accountId = $user->get('accountId');
        if (empty($accountId)) {
            return false;
        }

        $productsIds = $this->getEntityManager()->getRepository('Product')->getProductsIdsViaAccountId($accountId);
        if (empty($productsIds)) {
            return false;
        }

        return in_array($entity->get('id'), $productsIds);
    }
}

