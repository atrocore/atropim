<?php
declare(strict_types=1);

namespace Pim\Acl;

use Treo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Treo\Core\Utils\Util;

/**
 * Class ProductAttributeValue
 */
class ProductAttributeValue extends Base
{
    /**
     * @inheritDoc
     */
    public function checkIsOwner(User $user, Entity $entity)
    {
        if (empty($entity->get('isLocale'))) {
            return parent::checkIsOwner($user, $entity);
        }

        // get locale
        $locale = explode(\Pim\Services\ProductAttributeValue::LOCALE_IN_ID_SEPARATOR, $entity->id)[1];

        // prepare camelCase locale
        $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

        if ($user->id === $entity->get("ownerUser{$camelCaseLocale}Id")) {
            return true;
        }

        if ($user->id === $entity->get("assignedUser{$camelCaseLocale}Id")) {
            return true;
        }

        if ($user->id === $entity->get('createdById')) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function checkInTeam(User $user, Entity $entity)
    {
        // @todo develop for teams

        return true;
    }
}
