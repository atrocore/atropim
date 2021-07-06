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

namespace Pim\Core\Utils\FieldManager\Types;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

/**
 * Class Unit
 */
class Unit extends \Espo\Core\Utils\FieldManager\Types\Unit
{
    /**
     * @inheritDoc
     */
    public function prepareSqlUniqueData(Entity $entity, string $field): array
    {
        if ($entity->getEntityType() == 'ProductAttributeValue' && $field == 'value') {
            $table = Util::toUnderScore($entity->getEntityType());
            $dbField = Util::toUnderScore($field);
            $data = Json::encode($entity->get('data'));

            return [
                'select' => "$table.$dbField AS `$field`, $table.data AS `data`",
                'where' => "($table.$dbField = '" . $entity->get($field) . "' AND $table.data = '" . $data . "')"
            ];
        }

        return parent::prepareSqlUniqueData($entity, $field);
    }

    /**
     * @inheritDoc
     */
    public function checkEquals(Entity $entity, string $field, array $data): bool
    {
        if ($entity->getEntityType() == 'ProductAttributeValue' && $field == 'value') {
            return $entity->get($field) == $data[$field] && Json::encode($entity->get('data')) == $data['data'];
        }

        return parent::checkEquals($entity, $field, $data);
    }
}
