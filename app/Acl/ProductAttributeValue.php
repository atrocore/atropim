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
        // prepare camelCase locale
        $camelCaseLocale = '';
        if (!empty($entity->get('isLocale'))) {
            $locale = explode(\Pim\Services\ProductAttributeValue::LOCALE_IN_ID_SEPARATOR, $entity->id)[1];
            $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
        }

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
}
