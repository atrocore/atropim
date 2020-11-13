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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\ORM\EntityCollection;
use Treo\Core\Utils\Util;

/**
 * ProductAttributeValue service
 */
class ProductAttributeValue extends AbstractService
{
    public const LOCALE_IN_ID_SEPARATOR = '_l_';

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
     * @inheritDoc
     */
    public function getEntity($id = null)
    {
        /**
         * For attribute locale
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $id);
        if (count($parts) === 2) {
            $entity = parent::getEntity($parts[0]);
            if (!empty($entity)) {
                $locale = $parts[1];
                $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

                $entity->id = $id;
                $entity->set('isLocale', true);
                $entity->set('attributeName', $entity->get('attributeName') . ' › ' . $parts[1]);
                $entity->set('value', $entity->get("value{$camelCaseLocale}"));
            }

            return $entity;
        }

        return parent::getEntity($id);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        /**
         * For attribute locale
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $id);
        if (count($parts) === 2) {
            $id = $parts[0];
            if (isset($data->value)) {
                $data->{'value' . ucfirst(Util::toCamelCase(strtolower($parts[1])))} = $data->value;
                unset($data->value);
            }

            $data->isLocale = true;
        }

        // prepare data
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data->$k = Json::encode($v);
            }
        }

        return parent::updateEntity($id, $data);
    }

    /**
     * @param string $productId
     *
     * @return bool
     * @throws Forbidden
     */
    public function removeAllNotInheritedAttributes(string $productId): bool
    {
        // check acl
        if (!$this->getAcl()->check('ProductAttributeValue', 'remove')) {
            throw new Forbidden();
        }

        /** @var EntityCollection $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(
                [
                    'productId'                => $productId,
                    'productFamilyAttributeId' => null
                ]
            )
            ->find();

        if ($pavs->count() > 0) {
            foreach ($pavs as $pav) {
                if ($this->getAcl()->check($pav, 'remove')) {
                    try {
                        $this->getEntityManager()->removeEntity($pav);
                    } catch (BadRequest $e) {
                        // skip validation errors
                    }
                }
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        /**
         * For attribute locale
         */
        if (!empty($data->isLocale)) {
            $entity->skipValidation('requiredField');
        }
    }

    /**
     * @inheritDoc
     */
    protected function processActionHistoryRecord($action, Entity $entity)
    {
        /**
         * Skip if is attribute locale
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $entity->id);
        if (count($parts) === 2) {
            return;
        }

        parent::processActionHistoryRecord($action, $entity);
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
