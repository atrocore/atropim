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

namespace Pim\Import\Types\Simple\FieldConverters;

use Espo\Core\Exceptions\Error;
use Treo\Core\Utils\Util;

/**
 * Class EnumMultiLang
 */
class EnumMultiLang extends \Import\Types\Simple\FieldConverters\AbstractConverter
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter)
    {
        $value = (is_null($config['column']) || $row[$config['column']] == '') ? $config['default'] : $row[$config['column']];
        $inputRow->{$config['name']} = $value;

        if (isset($config['attributeId'])) {
            $attribute = $config['attribute'];

            $typeValue = $attribute->get('typeValue');
            $key = array_search($value, $typeValue);

            if ($key !== false) {
                foreach ($this->container->get('config')->get('inputLanguageList', []) as $locale) {
                    $locale = ucfirst(Util::toCamelCase(strtolower($locale)));

                    $inputRow->{$config['name'] . $locale} = $attribute->get('typeValue' . $locale)[$key];
                }
            } else {
                throw new Error("Not found any values for attribute '{$attribute->get('name')}'");
            }
        }
    }
}