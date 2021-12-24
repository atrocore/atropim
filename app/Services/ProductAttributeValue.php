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
use Treo\Core\EventManager\Event;

/**
 * Class ProductAttributeValue
 */
class ProductAttributeValue extends AbstractService
{
    public const LOCALE_IN_ID_SEPARATOR = '~';

    protected $mandatorySelectAttributeList
        = [
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
    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        /**
         * For attribute locale
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $id);
        if (count($parts) === 2) {
            $entity = $this->getRepository()->get($parts[0]);
            if (!empty($entity)) {
                $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($parts[1])));

                $entity->id = $id;
                $entity->set('isLocale', true);
                $entity->set('attributeName', $entity->get('attributeName') . ' › ' . $parts[1]);
                $entity->set('value', $entity->get("value{$camelCaseLocale}"));
                $entity->set('typeValue', $entity->get('attribute')->get("typeValue{$camelCaseLocale}"));

                // prepare owner user
                $ownerUser = $this->getEntityManager()->getEntity('User', $entity->get("ownerUser{$camelCaseLocale}Id"));
                if (!empty($ownerUser)) {
                    $entity->set('ownerUserId', $ownerUser->get('id'));
                    $entity->set('ownerUserName', $ownerUser->get('name'));
                } else {
                    $entity->set('ownerUserId', null);
                    $entity->set('ownerUserName', null);
                }

                // prepare assigned user
                $assignedUser = $this->getEntityManager()->getEntity('User', $entity->get("assignedUser{$camelCaseLocale}Id"));
                if (!empty($assignedUser)) {
                    $entity->set('assignedUserId', $assignedUser->get('id'));
                    $entity->set('assignedUserName', $assignedUser->get('name'));
                } else {
                    $entity->set('assignedUserId', null);
                    $entity->set('assignedUserName', null);
                }
            }
        } else {
            $entity = $this->getRepository()->get($id);
            $entity->set('typeValue', $entity->get('attribute')->get("typeValue"));
        }

        if (!empty($entity) && !empty($id)) {
            $this->loadAdditionalFields($entity);

            if (!$this->getAcl()->check($entity, 'read')) {
                throw new Forbidden();
            }
        }
        if (!empty($entity)) {
            $this->prepareEntityForOutput($entity);
        }

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
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

    /**
     * @inheritDoc
     */
    public function deleteEntity($id)
    {
        /**
         * Prepare ID for locale PAV
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $id);
        if (count($parts) === 2) {
            $id = $parts[0];
        }

        return parent::deleteEntity($id);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        // for asset attribute type
        $this->prepareValueForAssetType($data);

        /**
         * For attribute locale
         */
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, $id);
        if (count($parts) === 2) {
            // prepare camel case locale
            $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($parts[1])));

            /**
             * Set locale value
             */
            if (property_exists($data, 'value')) {
                $data->{"value{$camelCaseLocale}"} = $data->value;
                unset($data->value);

                if (property_exists($data, '_prev') && property_exists($data->_prev, 'value')) {
                    $data->_prev->{"value{$camelCaseLocale}"} = $data->_prev->value;
                    unset($data->_prev->value);
                }
            }

            /**
             * Set locale for asset type
             */
            if (property_exists($data, 'valueId')) {
                $data->{"value{$camelCaseLocale}Id"} = $data->valueId;
                unset($data->valueId);

                if (property_exists($data, 'valueName')) {
                    $data->{"value{$camelCaseLocale}Name"} = $data->valueName;
                    unset($data->valueName);
                }

                if (property_exists($data, '_prev') && property_exists($data->_prev, 'valueId')) {
                    $data->_prev->{"value{$camelCaseLocale}Id"} = $data->_prev->valueId;
                    unset($data->_prev->valueId);

                    if (property_exists($data->_prev, 'valueName')) {
                        $data->_prev->{"value{$camelCaseLocale}Name"} = $data->_prev->valueName;
                        unset($data->_prev->valueName);
                    }
                }
            }

            /**
             * Set locale ownerUser
             */
            if (isset($data->ownerUserId)) {
                $data->{"ownerUser{$camelCaseLocale}Id"} = $data->ownerUserId;
                unset($data->ownerUserId);

                if (isset($data->_prev) && property_exists($data->_prev, 'ownerUserId')) {
                    $data->_prev->{"ownerUser{$camelCaseLocale}Id"} = $data->_prev->ownerUserId;
                    unset($data->_prev->ownerUserId);
                }
            }

            /**
             * Set locale assignedUser
             */
            if (isset($data->assignedUserId)) {
                $data->{"assignedUser{$camelCaseLocale}Id"} = $data->assignedUserId;
                unset($data->assignedUserId);

                if (isset($data->_prev) && property_exists($data->_prev, 'assignedUserId')) {
                    $data->_prev->{"assignedUser{$camelCaseLocale}Id"} = $data->_prev->assignedUserId;
                    unset($data->_prev->assignedUserId);
                }
            }

            /**
             * Set locale teams
             */
            if (isset($data->teamsIds)) {
//                $this->getRepository()->changeMultilangTeams($id, 'ProductAttributeValue', $data->teamsIds);
                $data->{"teams{$camelCaseLocale}Ids"} = $data->teamsIds;
                unset($data->teamsIds);

                if (isset($data->_prev) && property_exists($data->_prev, 'teamsIds')) {
                    $data->_prev->{"teams{$camelCaseLocale}Ids"} = $data->_prev->teamsIds;
                    unset($data->_prev->teamsIds);
                }
            }

            if (isset($data->{'isInheritTeams' . $camelCaseLocale}) && $data->{'isInheritTeams' . $camelCaseLocale}) {
                $attributeId = $this->getRepository()->getMultilangAttributeId($parts[0], $parts[1]);

                if (!empty($attributeId)) {
                    $teamsIds = $this->getEntityManager()->getRepository('Attribute')->getAttributeTeams($attributeId['id']);

                    if (!empty($teamsIds)) {
                        $teamsIds = array_column($teamsIds, 'id');
                        $this->getRepository()->changeMultilangTeams($id, 'ProductAttributeValue', $teamsIds);
                    }
                }
            }

            $data->isLocale = true;
            $data->locale = $parts[1];

            // update id
            $id = $parts[0];
        }

        // prepare data
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data->$k = Json::encode($v);
            }
        }

        return parent::updateEntity($id, $data);
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
     * @inheritDoc
     */
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        /**
         * For attribute locale
         */
        if (!empty($data->isLocale)) {
            $entity->skipValidation('requiredField');
            $entity->locale = $data->locale ?? null;
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
        $parts = explode(self::LOCALE_IN_ID_SEPARATOR, (string)$entity->id);
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
        switch ($entity->get('attributeType')) {
            case 'array':
            case 'multiEnum':
                $entity->set('value', @json_decode($entity->get('textValue'), true));
                break;
            case 'text':
            case 'wysiwyg':
                $entity->set('value', $entity->get('textValue'));
                break;
            case 'bool':
                $entity->set('value', $entity->get('boolValue'));
                break;
            case 'currency':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueCurrency', $entity->get('varcharValue'));
                break;
            case 'unit':
                $entity->set('value', $entity->get('floatValue'));
                $entity->set('valueUnit', $entity->get('varcharValue'));
                break;
            case 'int':
                $entity->set('value', $entity->get('intValue'));
                break;
            case 'float':
                $entity->set('value', $entity->get('floatValue'));
                break;
            case 'date':
                $entity->set('value', $entity->get('dateValue'));
                break;
            case 'datetime':
                $entity->set('value', $entity->get('datetimeValue'));
                break;
            case 'asset':
                if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get('varcharValue')))) {
                    $entity->set('valueId', $attachment->get('id'));
                    $entity->set('valueName', $attachment->get('name'));
                    $entity->set('valuePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment));
                }
                break;
            default:
                $entity->set('value', $entity->get('varcharValue'));
                break;
        }

        $entity->clear('intValue');
        $entity->clear('boolValue');
        $entity->clear('dateValue');
        $entity->clear('datetimeValue');
        $entity->clear('floatValue');
        $entity->clear('varcharValue');
        $entity->clear('textValue');
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

        if (!empty($fields)) {
            if (!empty($data->isLocale) && !empty($data->locale)) {
                $locale = ucfirst(Util::toCamelCase(strtolower($data->locale)));
                foreach ($fields as $field => $translated) {
                    $fields[$field] = $this->getInjection('language')->translate($this->removeSuffix($field, $locale), 'fields', 'ProductAttributeValue');
                }
            }

            if (!empty($data->isProductUpdate)) {
                $fields = [$entity->get('id') => $entity->get('attributeName')];
                if (!empty($data->isLocale) && !empty($data->locale)) {
                    $fields = [$entity->get('id') . '_' . $data->locale => $entity->get('attributeName') . ' &rsaquo; ' . $data->locale];
                }
            }
        }

        return $fields;
    }

    /**
     * @param Entity $entity
     */
    protected function prepareEntity(Entity $entity): void
    {
        // exit if already prepared
        if (!empty($entity->get('attributeCode'))) {
            return;
        }

        if (empty($attribute = $entity->get('attribute'))) {
            throw new NotFound();
        }

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

        $this->convertValue($entity);
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
