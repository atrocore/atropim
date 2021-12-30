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
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;

class ProductAttributeValue extends AbstractService
{
    protected $mandatorySelectAttributeList
        = [
            'language',
            'mainLanguageId',
            'attributeId',
            'attributeName',
            'attributeType',
            'intValue',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'varcharValue',
            'textValue'
        ];

    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->prepareEntity($entity);
    }

    /**
     * @inheritDoc
     */
    public function createEntity($attachment)
    {
        // for asset attribute type
        $this->prepareValueForAssetType($attachment);

        return parent::createEntity($attachment);
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        $this->setInputValue($entity, $data);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        // for asset attribute type
        $this->prepareValueForAssetType($data);

        // prepare data
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data->$k = Json::encode($v);
            }
        }

        return parent::updateEntity($id, $data);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $this->setInputValue($entity, $data);
    }

    protected function setInputValue(Entity $entity, \stdClass $data): void
    {
        if (property_exists($data, 'value')) {
            // set attribute type if it needs
            if (empty($entity->get('attributeType')) && !empty($entity->get('attributeId'))) {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
                if (!empty($attribute)) {
                    $entity->set('attributeType', $attribute->get('type'));
                }
            }

            if (empty($entity->get('attributeType'))) {
                throw new BadRequest('No such attribute.');
            }

            switch ($entity->get('attributeType')) {
                case 'array':
                case 'multiEnum':
                case 'text':
                case 'wysiwyg':
                    $entity->set('textValue', $data->value);
                    break;
                case 'bool':
                    $entity->set('boolValue', $data->value);
                    break;
                case 'currency':
                    $entity->set('floatValue', $data->value);
                    if (property_exists($data, 'valueCurrency')) {
                        $entity->set('varcharValue', $data->valueCurrency);
                    }
                    break;
                case 'unit':
                    $entity->set('floatValue', $data->value);
                    if (property_exists($data, 'valueUnit')) {
                        $entity->set('varcharValue', $data->valueUnit);
                    }
                    break;
                case 'int':
                    $entity->set('intValue', $data->value);
                    break;
                case 'float':
                    $entity->set('floatValue', $data->value);
                    break;
                case 'date':
                    $entity->set('dateValue', $data->value);
                    break;
                case 'datetime':
                    $entity->set('datetimeValue', $data->value);
                    break;
                case 'asset':
                    $entity->set('varcharValue', $data->value);
                    break;
                default:
                    $entity->set('varcharValue', $data->value);
                    break;
            }
            $this->getRepository()->convertValue($entity);
        }
    }

    public function removeByTabAllNotInheritedAttributes(string $productId, string $tabId): bool
    {
        // check acl
        if (!$this->getAcl()->check('ProductAttributeValue', 'remove')) {
            throw new Forbidden();
        }

        $attributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->where([
                'attributeTabId' => empty($tabId) ? null : $tabId
            ])
            ->find();

        /** @var EntityCollection $pavs */
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(
                [
                    'productId' => $productId,
                    'attributeId' => array_column($attributes->toArray(), 'id')
                ]
            )
            ->find();

        foreach ($pavs as $pav) {
            if ($this->getAcl()->check($pav, 'remove')) {
                try {
                    $this->getEntityManager()->removeEntity($pav);
                } catch (BadRequest $e) {
                    // skip validation errors
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param array  $defs
     */
    protected function validateFieldWithPattern(Entity $entity, string $field, array $defs): void
    {
        if ($field == 'value' || ((!empty($multilangField = $defs['multilangField']) && $multilangField == 'value'))) {
            $attribute = !empty($entity->get('attribute')) ? $entity->get('attribute') : $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            $typesWithPattern = ['varchar'];

            if (in_array($attribute->get('type'), $typesWithPattern)
                && !empty($pattern = $attribute->get('pattern'))
                && !preg_match($pattern, $entity->get($field))) {
                $message = $this->getInjection('language')->translate('attributeDontMatchToPattern', 'exceptions', $entity->getEntityType());
                $message = str_replace('{attribute}', $attribute->get('name'), $message);
                $message = str_replace('{pattern}', $pattern, $message);

                throw new BadRequest($message);
            }
        } else {
            parent::validateFieldWithPattern($entity, $field, $defs);
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
     * @inheritDoc
     */
    protected function getRequiredFields(Entity $entity, \stdClass $data): array
    {
        $fields = parent::getRequiredFields($entity, $data);

        $values = ['value'];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $values[] = Util::toCamelCase('value_' . strtolower($locale));
        }

        $newFields = [];
        foreach ($fields as $field) {
            if (!in_array($field, $values)) {
                $newFields[] = $field;
            }
        }
        $fields = $newFields;

        return $fields;
    }

    /**
     * @inheritDoc
     */
    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        $this->prepareEntity($entity);

        if (in_array($entity->get('attributeType'), ['enum', 'multiEnum', 'unit'])) {
            return [];
        }

        $fields = parent::getFieldsThatConflict($entity, $data);

        if (!empty($fields) && property_exists($data, 'isProductUpdate') && !empty($data->isProductUpdate)) {
            $fields = [$entity->get('id') => $entity->get('attributeName')];
        }

        return $fields;
    }

    protected function prepareEntity(Entity $entity): void
    {
        // exit if already prepared
        if (!empty($entity->get('attributeCode'))) {
            return;
        }

        if (empty($attribute = $entity->get('attribute'))) {
            throw new NotFound();
        }

        $lang = '';
        if ($entity->get('language') !== 'main') {
            $entity->set('attributeName', $entity->get('attributeName') . ' › ' . $entity->get('language'));
            $lang = ucfirst(Util::toCamelCase(strtolower($entity->get('language'))));
        }

        $entity->set('typeValue', $attribute->get("typeValue$lang"));
        $entity->set('attributeAssetType', $attribute->get('assetType'));
        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
        $entity->set('attributeCode', $attribute->get('code'));
        $entity->set('prohibitedEmptyValue', false);
        $entity->set('isInherited', $this->isInheritedFromPf($entity->get('id')));
        $entity->set('prohibitedEmptyValue', $attribute->get('prohibitedEmptyValue'));
        $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
        $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
        $entity->set('sortOrder', $attribute->get('sortOrder'));
        $entity->set('channelCode', null);
        if (!empty($channel = $entity->get('channel'))) {
            $entity->set('channelCode', $channel->get('code'));
        }

        $this->getRepository()->convertValue($entity);

        $entity->clear('boolValue');
        $entity->clear('dateValue');
        $entity->clear('datetimeValue');
        $entity->clear('intValue');
        $entity->clear('floatValue');
        $entity->clear('varcharValue');
        $entity->clear('textValue');
    }

    private function prepareValueForAssetType(\stdClass $data): void
    {
        if (empty($data->value) && !empty($data->valueId)) {
            $data->value = $data->valueId;
        }
    }

    private function isInheritedFromPf(string $id): bool
    {
        if (empty($pav = $this->getRepository()->get($id))) {
            return false;
        }

        if (empty($product = $pav->get('product'))) {
            return false;
        }

        if (empty($product->get('productFamilyId'))) {
            return false;
        }

        $where = [
            'productFamilyId' => $product->get('productFamilyId'),
            'attributeId'     => $pav->get('attributeId'),
            'scope'           => $pav->get('scope'),
            'isRequired'      => !empty($pav->get('isRequired')),
        ];

        if ($where['scope'] === 'Channel') {
            $where['channelId'] = $pav->get('channelId');
        }

        $pfa = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['id'])
            ->where($where)
            ->findOne();

        return !empty($pfa);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $entity = $this->getRepository()->get($entity->get('id'));

        $this->prepareEntity($entity);

        return parent::isEntityUpdated($entity, $data);
    }

    protected function areValuesEqual(Entity $entity, string $field, $value1, $value2): bool
    {
        if (in_array($field, array_merge(['value'], array_values($this->getInputLanguageList())))) {
            $type = $entity->get('attributeType');
            $type = $this->getMetadata()->get(['fields', $type, 'fieldDefs', 'type'], $type);
        } else {
            $type = isset($entity->getFields()[$field]['type']) ? $entity->getFields()[$field]['type'] : 'varchar';
        }

        if ($type === Entity::JSON_ARRAY && is_string($value1)) {
            $value1 = Json::decode($value1, true);
        }

        if ($type === Entity::JSON_OBJECT && is_string($value1)) {
            $value1 = Json::decode($value1);
        }

        return Entity::areValuesEqual($type, $value1, $value2);
    }

    protected function getValueDataFields(): array
    {
        $fields = ['valueDataId'];

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $fields[] = 'valueData' . ucfirst(Util::toCamelCase(strtolower($language))) . 'Id';
            }
        }

        return $fields;
    }
}
