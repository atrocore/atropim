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

namespace Pim\Entities;

use Espo\Core\Templates\Entities\Hierarchy;

class Attribute extends Hierarchy
{
    public const DATA_FIELD = 'field';

    protected $entityType = "Attribute";

    public function _getMin(): ?float
    {
        return $this->getDataField('min');
    }

    public function _setMin(?float $min): void
    {
        $this->setDataField('min', $min);
    }

    public function _getMax(): ?float
    {
        return $this->getDataField('max');
    }

    public function _setMax(?float $max): void
    {
        $this->setDataField('max', $max);
    }

    public function _getMaxLength(): ?int
    {
        return $this->getDataField('maxLength');
    }

    public function _setMaxLength(?int $maxLength): void
    {
        $this->setDataField('maxLength', $maxLength);
    }

    public function _getCountBytesInsteadOfCharacters(): bool
    {
        return !empty($this->getDataField('countBytesInsteadOfCharacters'));
    }

    public function _setCountBytesInsteadOfCharacters(?bool $value): void
    {
        $this->setDataField('countBytesInsteadOfCharacters', !empty($value));
    }

    public function setDataField(string $key, $value): void
    {
        $data = $this->getData();
        $data[self::DATA_FIELD][$key] = $value;

        $this->set('data', $data);
        $this->valuesContainer[$key] = $value;
    }

    public function getDataField(string $key)
    {
        $data = $this->getDataFields();

        return isset($data[$key]) ? $data[$key] : null;
    }

    public function getDataFields(): array
    {
        $data = $this->getData();

        return isset($data[self::DATA_FIELD]) && is_array($data[self::DATA_FIELD]) ? $data[self::DATA_FIELD] : [];
    }

    public function getData(): array
    {
        $data = $this->get('data');

        return empty($data) ? [] : json_decode(json_encode($data), true);
    }

    public function setData(array $data): void
    {
        $this->set('data', $data);
    }

    public function toArray()
    {
        $res = parent::toArray();
        foreach ($this->getDataFields() as $name => $value) {
            $res[$name] = $value;
        }

        return $res;
    }
}
