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

use Espo\ORM\Entity;
use Import\Types\Simple\FieldConverters\Unit as DefaultUnit;

/**
 * Class Unit
 *
 * @author rr@atrocore.com
 */
class Unit extends DefaultUnit
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter)
    {
        if (isset($config['attributeId'])) {
            // prepare values
            $value = (!empty($config['column']) && $row[$config['column']] != '') ? $row[$config['column']] : $config['default'];
            $unit = (!empty($config['columnUnit']) && $row[$config['columnUnit']] != '') ? $row[$config['columnUnit']] : $config['defaultUnit'];

            // validate unit float value
            if (!is_null($value) && filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                throw new \Exception("Incorrect value for attribute '{$config['attribute']->get('name')}'");
            }

            // validate measuring unit
            if (!$this->validateUnit($unit, $entityType, $config)) {
                throw new \Exception("Incorrect measuring unit for attribute '{$config['attribute']->get('name')}'");
            }

            // prepare input row for attribute
            $inputRow->{$config['name']} = (float)$value;
            $inputRow->data = (object)['unit' => $unit];
        } else {
            parent::convert($inputRow, $entityType, $config, $row, $delimiter);
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareValue(\stdClass $restore, Entity $entity, array $item)
    {
        parent::prepareValue($restore, $entity, $item);
        if (isset($item['attributeId'])) {
            // prepare restore row for attribute
            $restore->data = $entity->get('data');
            unset($restore->{$item['name'].'Unit'});
        }
    }

    /**
     * @inheritDoc
     */
    protected function getMeasure(string $entityType, array $config): string
    {
        if (!isset($config['attributeId'])) {
            return parent::getMeasure($entityType, $config);
        } else {
            return $config['attribute']->get('typeValue')[0];
        }
    }
}
