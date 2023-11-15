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

namespace Pim\AclPortal;

use Espo\Core\AclPortal\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class ProductAttributeValue extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return $this->getAclManager()->checkScope($user, 'AttributeTab', $action) || $this->getAclManager()->checkScope($user, 'Attribute', $action);
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if (empty($entity->get('attributeId'))) {
            return false;
        }

        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        if (!empty($entity->get('attributeTabId')) && !empty($tab = $attribute->get('attributeTab'))) {
            return $this->getAclManager()->checkEntity($user, $tab, $action) && $this->getAclManager()->checkEntity($user, $attribute, $action);
        }

        return $this->getAclManager()->checkEntity($user, $attribute, $action);
    }
}
