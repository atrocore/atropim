<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\LayoutManager;
use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Pim\Core\ValueConverter;
use Atro\Services\Record;

class ProductAttributeValue extends AbstractProductAttributeService
{
    protected $foreignEntity = 'Product';
    protected $mandatorySelectAttributeList
        = [
            'language',
            'productId',
            'productName',
            'attributeId',
            'attributeName',
            'attributeType',
            'attributeTrim',
            'attributeTooltip',
            'intValue',
            'intValue1',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'floatValue1',
            'varcharValue',
            'textValue',
            'referenceValue'
        ];

    public function getGroupsPavs(string $productId, string $tabId, ?string $language, array $fieldFilter, ?array $languageFilter, array $scopeFilter): array
    {
        if (empty($productId)) {
            throw new NotFound();
        }

        $this->getPseudoTransactionManager()->runForEntity('Product', $productId);

        $user = $this->getUser();
        if ($language === null) {
            $language = Language::detectLanguage($this->getConfig(), $user);
        }

        if ($languageFilter === null) {
            $languageFilter = [];

            $mainLocaleCode = LayoutManager::getSystemMainLocaleCode($this->getConfig());

            foreach (LayoutManager::getUserLanguages($this->getUser(), $this->getEntityManager(), $this->getConfig()) as $locale) {
                if ($locale === $mainLocaleCode) {
                    $languageFilter[] = 'main';
                } else {
                    $languageFilter[] = $locale;
                }
            }

            array_unshift($languageFilter, 'unilingual');
        }

        $data = $this->getRepository()->getPavsWithAttributeGroupsData($productId, $tabId, $language, $languageFilter, $scopeFilter);

        /**
         * Prepare attributes groups
         */
        $groups = [];
        foreach ($data as $record) {
            if (!empty($record['attribute_data']['attribute_group_id'])) {
                $groups[] = [
                    'id'        => $record['attribute_data']['attribute_group_id'],
                    'key'       => $record['attribute_data']['attribute_group_id'],
                    'label'     => $record['attribute_data']['attribute_group_name'],
                    'sortOrder' => $record['attribute_data']['attribute_group_sort_order']
                ];
            }
        }
        $groups['no_group'] = [
            'id'        => null,
            'key'       => 'no_group',
            'label'     => (new Language($this->getInjection('container'), $language))->translate('noGroup', 'labels', 'Product'),
            'sortOrder' => PHP_INT_MAX
        ];
        usort($groups, function ($a, $b) {
            if ($a['sortOrder'] == $b['sortOrder']) {
                return 0;
            }
            return ($a['sortOrder'] < $b['sortOrder']) ? -1 : 1;
        });
        foreach ($groups as $group) {
            unset($group['sortOrder']);
            $result[$group['key']] = $group;
            $result[$group['key']] = $group;

        }
        unset($groups);

        /**
         * Prepare attributes groups attributes
         */
        foreach ($data as $record) {
            $tooltip = null;
            if ($language === 'main') {
                $tooltip = $record['attribute_data']['tooltip'];
            } elseif (!empty($record['attribute_data']['tooltip_' . ucwords($language)])) {
                $tooltip = $record['attribute_data']['tooltip_' . ucwords($language)];
            }

            $row = [
                'id'          => $record['id'],
                'channelName' => empty($record['channel_id']) ? '-9999' : $record['channel_name'],
                'language'    => $record['language'] === 'main' ? null : $record['language'],
                'tooltip'     => $tooltip,
                'attributeId' => $record['attribute_id'],
            ];

            if (!isset($result[$record['attribute_data']['attribute_group_id']])) {
                $key = 'no_group';
            } else {
                $key = $record['attribute_data']['attribute_group_id'];
            }
            $row['sortOrder'] = empty($record['attribute_data']['sort_order']) ? 0 : (int)$record['attribute_data']['sort_order'];

            $result[$key]['pavs'][] = $row;
        }

        foreach ($result as $key => $group) {
            if (empty($group['pavs'])) {
                unset($result[$key]);
                continue 1;
            }
            $pavs = $group['pavs'];
            array_multisort(
                array_column($pavs, 'sortOrder'), SORT_ASC,
                array_column($pavs, 'attributeId'), SORT_ASC,
                array_column($pavs, 'channelName'), SORT_ASC,
                array_column($pavs, 'language'), SORT_ASC,
                $pavs
            );
            $pavsSortedIds = array_column($pavs, 'id');
            $result[$key]['collection'] = [];

            $pavsRes = $this->findEntities([
                'where' => [
                    [
                        'type'      => 'in',
                        'attribute' => 'id',
                        'value'     => $pavsSortedIds
                    ]
                ]
            ]);

            if (isset($pavsRes['collection'])) {
                foreach ($pavsRes['collection'] as $pavEntity) {
                    // filter by field filter
                    if (!empty($fieldFilter) && !in_array('allValues', $fieldFilter)) {
                        switch ($pavEntity->get('attributeType')) {
                            case 'file':
                            case 'link':
                                $isEmpty = empty($pavEntity->get('valueId'));
                                break;
                            case 'int':
                            case 'float':
                                $isEmpty = $pavEntity->get('value') === null;
                                break;
                            case 'rangeInt':
                            case 'rangeFloat':
                                $isEmpty = $pavEntity->get('valueFrom') === null && $pavEntity->get('valueTo') === null;
                                break;
                            case 'array':
                            case 'extensibleMultiEnum':
                                $isEmpty = empty($pavEntity->get('value'));
                                break;
                            case 'linkMultiple':
                                $isEmpty = $pavEntity->get('valueIds');
                                break;
                            default:
                                $isEmpty = $pavEntity->get('value') === '' || $pavEntity->get('value') === null;
                        }

                        if (in_array('filled', $fieldFilter) && $isEmpty) {
                            continue;
                        } elseif (in_array('empty', $fieldFilter) && !$isEmpty) {
                            continue;
                        }

                        $isRequired = !empty($pavEntity->get('isRequired'));

                        if (in_array('required', $fieldFilter) && !$isRequired) {
                            continue;
                        } elseif (in_array('optional', $fieldFilter) && $isRequired) {
                            continue;
                        }
                    }

                    $result[$key]['collection'][] = array_merge($pavEntity->toArray(), [
                        'aclEdit'   => $this->getAclManager()->checkEntity($this->getUser(), $pavEntity, 'edit'),
                        'aclDelete' => $this->getAclManager()->checkEntity($this->getUser(), $pavEntity, 'delete'),
                    ]);
                }
            }

            usort($result[$key]['collection'], function ($a, $b) use ($pavsSortedIds) {
                $posA = array_search($a['id'], $pavsSortedIds);
                $posB = array_search($b['id'], $pavsSortedIds);
                return $posA - $posB;
            });

            $result[$key]['rowList'] = array_column($result[$key]['collection'], 'id');
            if (empty($result[$key]['rowList'])) {
                unset($result[$key]);
            }
            unset($result[$key]['pavs']);
        }

        $result = $this
            ->dispatchEvent('afterGetGroupPav', new Event(['result' => $result]))
            ->getArgument('result');

        return array_values($result);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        /**
         * Sort attribute values
         */
        $pavs = [];
        foreach ($collection as $k => $entity) {
            $row = [
                'entity'      => $entity,
                'channelName' => empty($entity->get('channelId')) ? '-9999' : $entity->get('channelName'),
                'language'    => $entity->get('language') === 'main' ? null : $entity->get('language')
            ];

            $attribute = $this->getRepository()->getPavAttribute($entity);

            $row['sortOrder'] = empty($attribute->get('sortOrder')) ? 0 : (int)$attribute->get('sortOrder');

            $pavs[$k] = $row;
        }

        array_multisort(
            array_column($pavs, 'sortOrder'), SORT_ASC,
            array_column($pavs, 'channelName'), SORT_ASC,
            array_column($pavs, 'language'), SORT_ASC,
            $pavs
        );

        foreach ($pavs as $k => $pav) {
            $collection->offsetSet($k, $pav['entity']);
        }

        parent::prepareCollectionForOutput($collection);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        $this->prepareEntity($entity);

        parent::prepareEntityForOutput($entity);
    }

    /**
     * @inheritDoc
     */
    public function createEntity($attachment)
    {
        if (!property_exists($attachment, 'attributeId')) {
            throw new BadRequest("'attributeId' is required.");
        }

        /**
         * Prepare maxLength
         */
        if (!property_exists($attachment, 'maxLength') || !property_exists($attachment, 'amountOfDigitsAfterComma')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($attachment->attributeId);
            if (empty($attribute)) {
                throw new BadRequest("Attribute '$attachment->attributeId' does not exist.");
            }

            if (!property_exists($attachment, 'maxLength') && in_array($attribute->get('type'), ['varchar', 'text', 'markdown', 'wysiwyg'])
                && $attribute->get('maxLength') !== null) {
                $attachment->maxLength = $attribute->get('maxLength');
                $attachment->countBytesInsteadOfCharacters = $attribute->get('countBytesInsteadOfCharacters');
            }

            if (!property_exists($attachment, 'amountOfDigitsAfterComma') && $attribute->get('type') === 'float'
                && $attribute->get('amountOfDigitsAfterComma') !== null) {
                $attachment->amountOfDigitsAfterComma = $attribute->get('amountOfDigitsAfterComma');
            }
        }


        /**
         * For multiple creation via languages
         */
        $this->prepareDefaultLanguages($attachment);
        if (property_exists($attachment, 'languages') && !empty($attachment->languages)) {
            return $this->multipleCreateViaLanguages($attachment);
        }

        $this->prepareDefaultValues($attachment);

        if ($this->isPseudoTransaction()) {
            return $this->originalCreateEntity($attachment);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return $this->originalCreateEntity($attachment);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return $this->originalCreateEntity($attachment);
        }

        $result = $this->originalCreateEntity($attachment);
        $this->createPseudoTransactionCreateJobs(clone $attachment);

        return $result;
    }

    protected function originalCreateEntity(\stdClass $attachment): Entity
    {
        $result = Record::createEntity($attachment);
        try {
            $this->createAssociatedAttributeValue($attachment, $attachment->attributeId);
        } catch (\Throwable $e) {
            // ignore errors
        }

        return $result;
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        /**
         * Inherit value from parent
         */
        if (
            !property_exists($data, 'value')
            && !property_exists($data, 'valueId')
            && !property_exists($data, 'valueIds')
            && !property_exists($data, 'valueUnitId')
            && !property_exists($data, 'valueCurrency')
            && !property_exists($data, 'valueFrom')
            && !property_exists($data, 'valueTo')
        ) {
            try {
                $this->inheritPav($entity);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('Inheriting of ProductAttributeValue failed: ' . $e->getMessage());
            }
        }
    }

    protected function createAssociatedAttributeValue(\stdClass $attachment, string $attributeId): void
    {
        $attrRepo = $this->getEntityManager()->getRepository('Attribute');

        $attribute = $attrRepo->get($attributeId);
        if (empty($attribute)) {
            return;
        }

        if (!$attribute->has('childrenIds')) {
            $attribute->set('childrenIds', $attribute->getLinkMultipleIdList('children'));
            $attrRepo->putToCache($attribute->get('id'), $attribute);
        }

        if (empty($attribute->get('childrenIds'))) {
            return;
        }

        foreach ($attribute->get('childrenIds') as $childId) {
            $aData = new \stdClass();
            $aData->attributeId = $childId;
            $aData->productId = $attachment->productId;

            $this->createEntity($aData);
        }
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (empty($data->_isCreateFromClassificationAttribute)) {
            $this->validateRequired($entity);
        }
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        if (!property_exists($data, 'attributeId')) {
            $entity = $this->getRepository()->get($id);
            if (!empty($entity)) {
                $data->attributeId = $entity->get('attributeId');
            }
        }

        if ($this->isPseudoTransaction()) {
            return Record::updateEntity($id, $data);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return Record::updateEntity($id, $data);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return Record::updateEntity($id, $data);
        }

        $this->createPseudoTransactionUpdateJobs($id, clone $data);
        return Record::updateEntity($id, $data);
    }

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        parent::handleInput($data, $id);

        if (property_exists($data, '_virtualValue') && is_object($data->_virtualValue)) {
            $data->_virtualValue = Json::decode(Json::encode($data->_virtualValue), true);
        }

        $this->getInjection('container')->get(ValueConverter::class)->convertTo($data, $this->getAttributeViaInputData($data, $id));
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $this->validateRequired($entity);
    }

    protected function hasCompleteness(Entity $entity): bool
    {
        if (!$this->getMetadata()->isModuleInstalled('Completeness')) {
            return false;
        }

        return !empty($this->getMetadata()->get(['scopes', 'Product', 'hasCompleteness']));
    }

    /**
     * @param Entity $entity
     *
     * @return void
     *
     * @throws BadRequest
     */
    protected function validateRequired(Entity $entity): void
    {
        if ($this->hasCompleteness($entity) || empty($entity->get('isRequired'))) {
            return;
        }

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));
        $checkEntity = clone $entity;

        $this->getInjection('container')->get(ValueConverter::class)->convertFrom($checkEntity, $attribute, false);

        if ($checkEntity->get('value') === null || $checkEntity->get('value') === '') {
            $field = $this->getInjection('language')->translate('value', 'fields', $entity->getEntityType());
            $message = $this->getInjection('language')->translate('fieldIsRequired', 'exceptions', $entity->getEntityType());

            throw new BadRequest(sprintf($message, $field));
        }
    }

    public function deleteEntity($id)
    {
        if (!empty($this->simpleRemove)) {
            return Record::deleteEntity($id);
        }

        if ($this->isPseudoTransaction()) {
            return Record::deleteEntity($id);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return Record::deleteEntity($id);
        }

        if (in_array('productAttributeValues', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return Record::deleteEntity($id);
        }

        $this->createPseudoTransactionDeleteJobs($id);
        return Record::deleteEntity($id);
    }

    public function linkAttribute(array $params, string $productId): array
    {
        if (isset($params['ids'])) {
            $ids = $params['ids'];
        } else {
            $selectParams = $this->getSelectManagerFactory()->create('Attribute')->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository('Attribute')->handleSelectParams($selectParams);
            $attributes = $this->getEntityManager()->getRepository('Attribute')->find(array_merge($selectParams, ['select' => ['id']]))->toArray();

            $ids = array_column($attributes, 'id');
        }

        $success = 0;

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $pav = new \stdClass();
                $pav->productId = $productId;
                $pav->attributeId = $id;
                try {
                    $this->createEntity($pav);
                    $success++;
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        return ['message' => str_replace('{count}', $success . '', $this->getInjection('language')->translate('countOfLinkedAttributes', 'labels', 'ProductAttributeValue'))];
    }

    public function linkAttributeGroup(array $params, string $productId): array
    {
        if (isset($params['ids'])) {
            $ids = $params['ids'];
        } else {
            $selectParams = $this->getSelectManagerFactory()->create('AttributeGroup')->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository('AttributeGroup')->handleSelectParams($selectParams);
            $groups = $this->getEntityManager()->getRepository('AttributeGroup')->find(array_merge($selectParams, ['select' => ['id']]))->toArray();

            $ids = array_column($groups, 'id');
        }

        $params['attributeWhere'][] = [
            'type'      => 'in',
            'attribute' => 'attributeGroupId',
            'value'     => $ids
        ];

        return $this->linkAttribute(['where' => $params['attributeWhere']], $productId);
    }

    /**
     * @param string $attributeGroupId
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function unlinkAttributeGroup(string $attributeGroupId, string $productId, bool $hierarchically = false): bool
    {
        $attributes = $this
            ->getRepository()
            ->select(['id'])
            ->join('attribute')
            ->where([
                'attribute.attributeGroupId' => $attributeGroupId,
                'productId'                  => $productId
            ])
            ->find()
            ->toArray();

        if (!$hierarchically) {
            $this->simpleRemove = true;
        }

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                try {
                    $this->deleteEntity($attribute['id']);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('AttributeGroup hierarchical removing from Product failed: ' . $e->getMessage());
                }
            }
        }

        return true;
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
                    'productId'   => $productId,
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

        $input = clone $data;

        if (property_exists($input, '_virtualValue')) {
            foreach ($input->_virtualValue as $name => $value) {
                $input->$name = $value;
            }
        }

        $fields = parent::getFieldsThatConflict($entity, $input);

        if (!empty($fields) && property_exists($input, 'isProductUpdate') && !empty($input->isProductUpdate)) {
            $fields = [$entity->get('id') => $entity->get('attributeName')];
        }

        foreach (['id', 'unit', 'unitId'] as $item) {
            if (isset($fields['value' . ucfirst($item)])) {
                unset($fields['value' . ucfirst($item)]);
            }
        }

        return $fields;
    }

    public function prepareEntity(Entity $entity, bool $clear = true): void
    {
        $attribute = $this->getRepository()->getPavAttribute($entity);

        if (empty($attribute)) {
            throw new NotFound();
        }

        if (!empty($userLanguage = $this->getInjection('user')->getLanguage())) {
            $nameField = Util::toCamelCase('name_' . strtolower($userLanguage));
            if ($attribute->has($nameField) && !empty($attribute->get($nameField))) {
                $entity->set('attributeName', $attribute->get($nameField));
            }
            $tooltipFieldName = Util::toCamelCase('tooltip_' . strtolower($userLanguage));
            if ($attribute->has($tooltipFieldName) && !empty($attribute->get($tooltipFieldName))) {
                $tooltip = $attribute->get($tooltipFieldName);
            }
        }

        if (empty($tooltip)) {
            $tooltip = $attribute->get('tooltip');
        }
        $entity->set('attributeTooltip', $tooltip);
        $entity->set('attributeEntityType', $attribute->get('entityType'));
        $entity->set('attributeEntityField', $attribute->get('entityField'));
        $entity->set('attributeFileTypeId', $attribute->get('fileTypeId'));
        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
        $entity->set('attributeCode', $attribute->get('code'));
        $entity->set('prohibitedEmptyValue', $attribute->get('prohibitedEmptyValue'));
        $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
        $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
        $entity->set('attributeNotNull', $attribute->get('notNull'));
        $entity->set('attributeTrim', $attribute->get('trim'));


        if (!empty($attribute->get('useDisabledTextareaInViewMode')) && in_array($entity->get('attributeType'), ['text', 'varchar', 'wysiwyg'])) {
            $entity->set('useDisabledTextareaInViewMode', $attribute->get('useDisabledTextareaInViewMode'));
        }

        $entity->set('sortOrder', $attribute->get('sortOrder'));

        $entity->set('channelCode', null);
        if (!empty($entity->get('channelId')) && !empty($channel = $entity->get('channel'))) {
            $entity->set('channelCode', $channel->get('code'));
        }

        if (empty($this->getMemoryStorage()->get('exportJobId')) && empty($this->getMemoryStorage()->get('importJobId'))) {
            $classificationAttribute = $this->getRepository()->findClassificationAttribute($entity);

            $this->getRepository()->prepareAttributeData($attribute, $entity, $classificationAttribute);

            $entity->set('isPavRelationInherited', !empty($classificationAttribute));

            if ($this->getRepository()->isPavRelationInherited($entity)) {
                $entity->set('isPavValueInherited', $this->getRepository()->isPavValueInherited($entity));
            }
        }

        $this->getInjection('container')->get(ValueConverter::class)->convertFrom($entity, $attribute, $clear);

        if ($attribute->get('measureId')) {
            $entity->set('attributeMeasureId', $attribute->get('measureId'));
            $this->prepareUnitFieldValue($entity, 'value', [
                'measureId' => $attribute->get('measureId'),
                'type'      => $attribute->get('type'),
                'mainField' => 'value'
            ]);
        }

        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
        if (in_array($entity->get('attributeType'), array_keys($dropdownTypes))) {
            $entity->set('attributeIsDropdown', $attribute->get('dropdown'));
        }

        if ($entity->get('channelId') === '') {
            $entity->set('channelId', null);
        }
    }

    protected function prepareInputForAddOnlyMode(string $id, \stdClass $data): void
    {
        $needToPrepareValue = property_exists($data, 'valueAddOnlyMode') && !empty($data->valueAddOnlyMode);
        if ($needToPrepareValue) {
            unset($data->valueAddOnlyMode);
        }

        parent::prepareInputForAddOnlyMode($id, $data);

        if ($needToPrepareValue) {
            $pav = $this->getEntityManager()->getRepository('ProductAttributeValue')->get($id);
            if (empty($pav)) {
                return;
            }

            switch ($pav->get('attributeType')) {
                case 'array':
                case 'extensibleMultiEnum':
                    $inputValue = is_string($data->value) ? @json_decode((string)$data->value) : $data->value;
                    if (!is_array($inputValue)) {
                        $inputValue = [];
                    }

                    $was = @json_decode((string)$pav->get('textValue'));
                    if (!is_array($was)) {
                        $was = [];
                    }

                    $preparedValue = array_merge($was, $inputValue);
                    $preparedValue = array_unique($preparedValue);

                    $data->value = json_encode($preparedValue);
                    break;
            }
        }
    }

    protected function prepareDefaultValues(\stdClass $data): void
    {
        if (property_exists($data, 'attributeId') && !empty($data->attributeId)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data->attributeId);
        }

        if (empty($attribute)) {
            return;
        }

        if (!property_exists($data, 'channelId')) {
            if (!empty($attribute->get('defaultChannelId'))) {
                $data->channelId = $attribute->get('defaultChannelId');
            }
        }
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $fetched = property_exists($entity, '_fetchedEntity') ? $entity->_fetchedEntity : $this->getRepository()->where(['id' => $entity->get('id')])->findOne(["noCache" => true]);
        return parent::isEntityUpdated($fetched, $data);
    }

    public function updatePanelFromProduct(string $productId, \stdClass $data)
    {
        // input data
        $productAttributeValues = new \stdClass();
        // pavs to update
        $pavs = new EntityCollection();
        $conflicts = [];

        // For Mass Update
        if (property_exists($data, '_isMassUpdate')) {
            $attributeIds = array_column((array)$data->panelsData->productAttributeValues, 'attributeId');
            $existingPavs = $this->getRepository()->where(['productId' => $productId, 'attributeId' => $attributeIds])->find();

            foreach ($data->panelsData->productAttributeValues as $pavData) {
                $existPav = $this->getProductAttributeForUpdating($existingPavs, $pavData);
                try {
                    if (!is_null($existPav)) {
                        $productAttributeValues->{$existPav->get('id')} = $pavData;
                        $pavs->append($existPav);
                    } else {
                        $copy = clone $pavData;

                        if (property_exists($copy, 'createPavIfNotExists')) {
                            if (empty($copy->createPavIfNotExists)) {
                                continue 1;
                            }
                            unset($copy->createPavIfNotExists);
                        }

                        $copy->productId = $productId;

                        $result = $this->createEntity($copy);

                        if (!$result->get('attributeIsMultilang')) {
                            $existingPavs->append($result);
                        } else {
                            $existingPavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['productId' => $productId, 'attributeId' => $attributeIds])
                                ->find();
                        }

                    }
                } catch (Conflict $e) {
                    $conflicts = array_merge($conflicts, $e->getFields());
                }
            }
        } // For Single Product Update
        else {
            $ids = array_keys((array)$data->panelsData->productAttributeValues);
            $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['id' => $ids])->find();
            $productAttributeValues = $data->panelsData->productAttributeValues;
        }

        $pavs = $this
            ->dispatchEvent('beforeUpdatePavs', new Event(['id' => $productId, 'pavsData' => $productAttributeValues, 'pavs' => $pavs, 'service' => $this]))
            ->getArgument('pavs');

        foreach ($pavs as $pav) {
            if (!empty($this->getMetadata()->get(['attributes', $pav->get('attributeType'), 'isValueReadOnly']))) {
                continue;
            }

            // prepare input
            $input = clone $productAttributeValues->{$pav->get('id')};
            if (property_exists($data, '_ignoreConflict') && !empty($data->_ignoreConflict) && property_exists($data, '_prev')) {
                unset($input->_prev);
            }
            foreach (['attributeId', 'channelId', 'language'] as $field) {
                if (property_exists($input, $field)) {
                    unset($input->$field);
                }
            }
            $input->isProductUpdate = true;
            try {
                $this->updateEntity($pav->get('id'), $input);
            } catch (Conflict $e) {
                $conflicts = array_merge($conflicts, $e->getFields());
            } catch (NotModified $e) {
                // ignore
            }
        }

        return $conflicts;
    }

    protected function getProductAttributeForUpdating(EntityCollection $pavs, \stdClass $data): ?Entity
    {
        foreach ($pavs as $pav) {
            if ($pav->get('attributeId') == $data->attributeId && $pav->get('language') == $data->language && $pav->get('channelId') == $data->channelId) {
                return $pav;
            }
        }

        return null;
    }
}
