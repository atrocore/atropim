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

namespace Pim\Services;

use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Treo\Core\Utils\Util;

/**
 * ProductAttributeValue service
 */
class ProductAttributeValue extends AbstractService
{
    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('isCustom', $this->isCustom($entity));
        $entity->set('attributeType', !empty($entity->get('attribute')) ? $entity->get('attribute')->get('type') : null);
        $entity->set('attributeIsMultilang', !empty($entity->get('attribute')) ? $entity->get('attribute')->get('isMultilang') : false);

        $this->convertValue($entity);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        // prepare data
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data->$k = Json::encode($v);
            }
        }

        return parent::updateEntity($id, $data);
    }

    /**
     * @param Entity $entity
     */
    protected function convertValue(Entity $entity)
    {
        $type = $entity->get('attributeType');

        if (!empty($type)) {
            switch ($type) {
                case 'array':
                    $entity->set('value', ((string)$entity->get('value') === '') ? null : Json::decode($entity->get('value'), true));
                    break;
                case 'bool':
                    $entity->set('value', ((string)$entity->get('value') === '1' || (string)$entity->get('value') === 'true'));
                    foreach ($this->getInputLanguageList() as $multiLangField) {
                        $entity->set($multiLangField, ((string)$entity->get($multiLangField) === '1' || (string)$entity->get($multiLangField) === 'true'));
                    }
                    break;
                case 'int':
                    $entity->set('value', ((string)$entity->get('value') === '') ? null : (int)$entity->get('value'));
                    break;
                case 'unit':
                case 'float':
                    $entity->set('value', ((string)$entity->get('value') === '') ? null : (float)$entity->get('value'));
                    break;
                case 'multiEnum':
                    $entity->set('value', ((string)$entity->get('value') === '') ? null : Json::decode($entity->get('value'), true));
                    foreach ($this->getInputLanguageList() as $multiLangField) {
                        $entity->set($multiLangField, ((string)$entity->get($multiLangField) === '') ? null : Json::decode($entity->get($multiLangField), true));
                    }
                    break;
            }
        }
    }

    /**
     * @return array
     */
    protected function getInputLanguageList(): array
    {
        // prepare result
        $result = [];

        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                $result[$locale] = Util::toCamelCase('value_' . strtolower($locale));
            }
        }

        return $result;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    private function isCustom(Entity $entity): bool
    {
        // prepare is custom field
        $isCustom = true;

        if (!empty($productFamilyAttribute = $entity->get('productFamilyAttribute'))
            && !empty($productFamilyAttribute->get('productFamily'))) {
            $isCustom = false;
        }

        return $isCustom;
    }
}
