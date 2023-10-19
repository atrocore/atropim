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

namespace Pim\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Attribute extends \Espo\Core\Acl\Base
{
    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!empty($attributeTab = $entity->get('attributeTab'))) {
            if (!$this->getAclManager()->checkEntity($user, $attributeTab, 'read')) {
                return false;
            }
        }

        return $this->checkEntity($user, $entity, $data, 'read');
    }
}

