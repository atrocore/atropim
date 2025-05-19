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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\Base;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class Attribute extends Base
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'extensibleEnumId', 'data', 'measureId', 'defaultUnit'];

    public function getAttributesDefs(string $entityName, array $attributesIds): array
    {
        $attributes = $this->getRepository()->getAttributesByIds($attributesIds);

        $entity = $this->getEntityManager()->getRepository($entityName)->get();

        /** @var AttributeFieldConverter $converter */
        $converter = $this->getInjection(AttributeFieldConverter::class);

        $attributesDefs = [];
        foreach ($attributes as $row) {
            $converter->getFieldType($row['type'])->convert($entity, $row, $attributesDefs);
        }

        return $attributesDefs;
    }

    public function createAttributeValue(array $pseudoTransactionData): void
    {
        $entityName = $pseudoTransactionData['entityName'] ?? null;
        $entityId = $pseudoTransactionData['entityId'] ?? null;
        $data = $pseudoTransactionData['data'] ?? [];

        if (empty($entityName) || empty($entityId) || empty($data)) {
            return;
        }

        $entity = $this->getEntityManager()->getRepository($entityName)->get($entityId);
        if (empty($entity)) {
            return;
        }

        /** @var \Pim\Repositories\Attribute $attributeRepo */
        $attributeRepo = $this->getEntityManager()->getRepository('Attribute');

        /** @var AttributeFieldConverter $converter */
        $converter = $this->getInjection(AttributeFieldConverter::class);

        $attributesDefs = [];
        foreach ($this->getRepository()->getAttributesByIds([$data['attributeId']]) as $row) {
            $converter->getFieldType($row['type'])->convert($entity, $row, $attributesDefs);
        }

        $attributeFieldName = AttributeFieldConverter::prepareFieldName($data['attributeId']);

        // set null value
        foreach ($entity->fields ?? [] as $field => $fieldDefs) {
            $valueName = str_replace($attributeFieldName, 'value', $field);
            if (!empty($fieldDefs['attributeId']) && !empty($fieldDefs['column']) && !array_key_exists($valueName, $data)) {
                $data[$valueName] = null;
            }
        }

        // set default value if it needs
        foreach (['value', 'valueFrom', 'valueTo', 'valueUnitId', 'valueId', 'valueIds'] as $key) {
            if (array_key_exists($key, $data)) {
                $fieldName = str_replace('value', $attributeFieldName, $key);
                $attributeRepo->upsertAttributeValue($entity, $fieldName, $data[$key]);
            }
        }
    }

    public function addAttributeValue(string $entityName, string $entityId, ?array $where, ?array $ids): bool
    {
        if ($where !== null) {
            $selectParams = $this
                ->getSelectManager()
                ->getSelectParams(['where' => json_decode(json_encode($where), true)], true);
            $attributes = $this->getRepository()->find($selectParams);
        } elseif ($ids !== null) {
            $attributes = $this->getRepository()->where(['id' => $ids])->find();
        }

        if (empty($attributes)) {
            return false;
        }

        foreach ($attributes as $attribute) {
            try {
                $this->getRepository()->addAttributeValue($entityName, $entityId, $attribute->get('id'));
            } catch (UniqueConstraintViolationException $e) {
                // ignore
            }

            if ($attribute->get('type') === 'composite') {
                $childrenIds = [];
                $this
                    ->getRepository()
                    ->prepareAllChildrenAttributesIdsForComposite($attribute->get('id'), $childrenIds);
                foreach ($childrenIds as $childId) {
                    try {
                        $this->getRepository()->addAttributeValue($entityName, $entityId, $childId);
                    } catch (UniqueConstraintViolationException $e) {
                        // ignore
                    }
                }
            }
        }

        return true;
    }

    public function removeAttributeValue(string $entityName, string $entityId, string $attributeId): bool
    {
        $attribute = $this->getRepository()->get($attributeId);

        if (
            !empty($compositeAttribute = $attribute->get('compositeAttribute'))
            && $this->getRepository()->hasAttributeValue($entityName, $entityId, $compositeAttribute->get('id'))
        ) {
            throw new BadRequest('Nested attribute cannot be deleted.');
        }

        if (!empty($attribute)) {
            if ($attribute->get('type') === 'composite') {
                $childrenIds = [];
                $this
                    ->getRepository()
                    ->prepareAllChildrenAttributesIdsForComposite($attribute->get('id'), $childrenIds);
                foreach ($childrenIds as $childId) {
                    $this->getRepository()->removeAttributeValue($entityName, $entityId, $childId);
                }
            }
        }

        return $this->getRepository()->removeAttributeValue($entityName, $entityId, $attributeId);
    }

    /**
     * @inheritDoc
     */
    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        $entity = $this->getRepository()->get($id);

        if (!empty($entity) && $this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                if (!empty($ownerUserId = $entity->get("ownerUser{$camelCaseLocale}Id"))) {
                    $ownerUser = $this->getEntityManager()->getEntity('User', $ownerUserId);
                    $entity->set("ownerUser{$camelCaseLocale}Name", $ownerUser->get('name'));
                } else {
                    $entity->set("ownerUser{$camelCaseLocale}Name", null);
                }

                if (!empty($assignedUserId = $entity->get("assignedUser{$camelCaseLocale}Id"))) {
                    $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                    $entity->set("assignedUser{$camelCaseLocale}Name", $assignedUser->get('name'));
                } else {
                    $entity->set("assignedUser{$camelCaseLocale}Name", null);
                }
            }
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

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
        if (in_array($entity->get('type'), array_keys($dropdownTypes)) && $entity->get('dropdown') === null) {
            $entity->set('dropdown', false);
        }

        if (in_array($entity->get('type'),
                ['extensibleEnum', 'extensibleMultiEnum']) && $entity->get('extensibleEnum') !== null) {
            $entity->set('listMultilingual', $entity->get('extensibleEnum')->get('multilingual'));
        }

        if (!empty($entity->get('htmlSanitizerId'))) {
            $htmlSanitizer = $this->getEntityManager()->getRepository('HtmlSanitizer')->get($entity->get('htmlSanitizerId'));
            if (!empty($htmlSanitizer)) {
                $entity->set('htmlSanitizerName', $htmlSanitizer->get('name'));
            }
        }

        if ($entity->get('fullWidth') === null) {
            $entity->set('fullWidth', in_array($entity->get('type'), ['wysiwyg', 'markdown', 'text', 'composite']));
        }
    }

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        if (property_exists($data, 'sortOrder') && property_exists($data, '_sortedIds')) {
            $this->getRepository()->updateSortOrderInAttributeGroup($data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    protected function init()
    {
        parent::init();

        // add dependencies
        $this->addDependency('language');
        $this->addDependency(AttributeFieldConverter::class);
    }

    /**
     * Get multilang fields
     *
     * @return array
     */
    protected function getMultilangFields(): array
    {
        // get config
        $config = $this->getConfig()->get('modules');

        return (!empty($config['multilangFields'])) ? array_keys($config['multilangFields']) : [];
    }

    public function findEntities($params)
    {
        if (!empty(\Atro\Services\AbstractService::getLanguagePrism())) {
            return parent::findEntities($params);
        }

        $shouldUseLanguagePrism = true;

        if (!empty($params['select']) && $this->getConfig()->get('isMultilangActive')) {
            $userLanguage = $this->getInjection('user')->getLanguage();
            if (!empty($userLanguage) && in_array($userLanguage, $this->getConfig()->get('inputLanguageList'))) {
                foreach ($this->getConfig()->get('inputLanguageList') as $language) {
                    if ($language === 'main' || $language == $this->getConfig()->get('mainLanguage')) {
                        continue;
                    }
                    if (in_array(Util::toCamelCase('name_' . strtolower($language)), $params['select'])) {
                        $shouldUseLanguagePrism = false;
                    }
                }
            } else {
                $shouldUseLanguagePrism = false;
            }

            if ($shouldUseLanguagePrism) {
                $GLOBALS['languagePrism'] = $userLanguage;
            }
        }

        return parent::findEntities($params);
    }

    protected function checkFieldsWithPattern(Entity $entity): void
    {
        $attributeList = array_keys($this->getInjection('metadata')->get(['attributes']));
        if (!in_array($entity->get('type'), $attributeList)) {
            throw new Forbidden(str_replace('{type}', $entity->get('type'),
                $this->getInjection('language')->translate('invalidType', 'exceptions', 'Attribute')));
        }

        parent::checkFieldsWithPattern($entity);
    }

    protected function getTranslatedNameField(): string
    {
        $language = \Espo\Core\Services\Base::getLanguagePrism();
        if (empty($language)) {
            $language = $this->getInjection('user')->getLanguage();
        }
        if (!empty($language) && $language !== 'main') {
            if ($this->getConfig()->get('isMultilangActive') && in_array($language,
                    $this->getConfig()->get('inputLanguageList', []))) {
                return Util::toCamelCase('name_' . strtolower($language));
            }
        }

        return 'name';
    }
}
