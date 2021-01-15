<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

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
