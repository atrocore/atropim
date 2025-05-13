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

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\ValueConverter;

class ClassificationAttribute extends AbstractProductAttributeService
{
    protected $foreignEntity = 'Classification';
    protected $mandatorySelectAttributeList
        = [
            'classificationId',
            'attributeId',
            'attributeName',
            'isRequired',
            'intValue',
            'intValue1',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'floatValue1',
            'varcharValue',
            'referenceValue',
            'textValue',
            'data'
        ];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));

        if (!empty($attribute)) {
            $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
            $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
            $entity->set('attributeEntityType', $attribute->get('entityType'));
            $entity->set('attributeEntityField', $attribute->get('entityField'));
            $entity->set('attributeFileTypeId', $attribute->get('fileTypeId'));
            $entity->set('attributeNotNull', $attribute->get('notNull'));
            $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
            $entity->set('sortOrder', $attribute->get('sortOrder'));
            if (!empty($this->getConfig()->get('isMultilangActive'))) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
                    $entity->set('attributeName' . $preparedLocale, $attribute->get('name' . $preparedLocale));
                }
            }

            if (empty($this->getMemoryStorage()->get('exportJobId'))
                && empty($this->getMemoryStorage()->get('importJobId'))
                && $this->getMetadata()->get(['scopes','Classification','type']) === 'Hierarchy'
            ) {
                $entity->set('isCaRelationInherited', $this->getRepository()->isPavRelationInherited($entity));

                if ($entity->get('isCaRelationInherited')) {
                    $entity->set('isCaValueInherited', $this->getRepository()->isPavValueInherited($entity));
                }
            }

//            $this->getInjection(ValueConverter::class)->convertFrom($entity, $attribute);

            if ($attribute->get('measureId')) {
                $entity->set('attributeMeasureId', $attribute->get('measureId'));
                $this->prepareUnitFieldValue($entity, 'value', [
                    'measureId' => $attribute->get('measureId'),
                    'type'      => $attribute->get('type'),
                    'mainField' => 'value'
                ]);
            }
        }
    }

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        parent::handleInput($data, $id);

//        $this->getInjection(ValueConverter::class)->convertTo($data, $this->getAttributeViaInputData($data, $id));
    }

    public function createEntity($attachment)
    {
        if (!property_exists($attachment, 'attributeId')) {
            throw new BadRequest("'attributeId' is required.");
        }

        /**
         * For multiple creation via languages
         */
        $this->prepareDefaultLanguages($attachment);
        if (property_exists($attachment, 'languages') && !empty($attachment->languages)) {
            return $this->multipleCreateViaLanguages($attachment);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $this->prepareDefaultValues($attachment);
            $result = parent::createEntity($attachment);
            $this->createPseudoTransactionCreateJobs($attachment);

            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function prepareDefaultValues(\stdClass $data): void
    {
        if (property_exists($data, 'attributeId') && !empty($data->attributeId)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data->attributeId);
        }

        if (empty($attribute)) {
            return;
        }

        foreach (['maxLength', 'isRequired', 'countBytesInsteadOfCharacters', 'min', 'max'] as $field) {
            if (!property_exists($data, $field)) {
                $data->$field = $attribute->get($field);
            }
        }

        if (!property_exists($data, 'maxLength')) {
            $data->maxLength = $attribute->get('maxLength');
            $data->countBytesInsteadOfCharacters = $attribute->get('countBytesInsteadOfCharacters');
        }
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        return;

        if (!property_exists($data, 'classificationId')) {
            return;
        }

        foreach ($this->getRepository()->getProductChannelsViaClassificationId($data->classificationId) as $id) {
            $inputData = clone $data;
            $inputData->productId = $id;
            $inputData->_isCreateFromClassificationAttribute = true;
            unset($inputData->classificationId);

            $parentId = $this->getPseudoTransactionManager()->pushCreateEntityJob('ProductAttributeValue', $inputData);
            $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $inputData->productId, null, $parentId);
        }

        if($this->getMetadata()->get(['scopes','Classification','type']) === 'Hierarchy'){
            parent::createPseudoTransactionCreateJobs($data,$parentTransactionId);
        }
    }

    public function updateEntity($id, $data)
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionUpdateJobs($id, clone $data);
            $result = parent::updateEntity($id, $data);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data, $parentTransactionId = null): void
    {
        return;

        foreach ($this->getRepository()->getInheritedPavs($id) as $pav) {
            $inputData = new \stdClass();
            foreach (['channelId', 'language'] as $key) {
                if (property_exists($data, $key)) {
                    $inputData->$key = $data->$key;
                }
            }

            if (!empty((array)$inputData)) {
                $parentId = $this->getPseudoTransactionManager()->pushUpdateEntityJob('ProductAttributeValue', $pav->get('id'), $inputData);
                $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $pav->get('productId'), null, $parentId);
            }
        }

        if($this->getMetadata()->get(['scopes','Classification','type']) === 'Hierarchy'){
            parent::createPseudoTransactionUpdateJobs($id, $data, $parentTransactionId);
        }
    }

    public function deleteEntityWithThemPavs($id)
    {
        /**
         * ID can be an array with one item. It is needs to execute this method from custom pseudo transaction in advanced classification module
         */
        if (is_array($id)) {
            $id = array_shift($id);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionDeleteJobs($id);
            $result = parent::deleteEntity($id);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    public function sortCollection(EntityCollection $collection): void
    {
        $attributes = [];
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['id' => array_column($collection->toArray(), 'attributeId')])->find() as $attribute) {
            $attributes[$attribute->get('id')] = $attribute;
        }

        $records = [];
        foreach ($collection as $k => $entity) {
            $row = [
                'entity'      => $entity,
                'channelName' => empty($entity->get('channelId')) ? '-9999' : $entity->get('channelName'),
                'language'    => $entity->get('language') === 'main' ? null : $entity->get('language')
            ];

            $attribute = $attributes[$entity->get('attributeId')];

                $row['sortOrder'] = empty($attribute->get('sortOrder')) ? 0 : (int)$attribute->get('sortOrder');

            $records[$k] = $row;
        }

        array_multisort(
            array_column($records, 'sortOrder'), SORT_ASC,
            array_column($records, 'channelName'), SORT_ASC,
            array_column($records, 'language'), SORT_ASC,
            $records
        );

        foreach ($records as $k => $record) {
            $collection->offsetSet($k, $record['entity']);
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->sortCollection($collection);

        parent::prepareCollectionForOutput($collection);
    }

    public function unlinkAttributeGroupHierarchy(string $attributeGroupId, string $classificationId): bool
    {
        $attributes = $this
            ->getRepository()
            ->select(['id'])
            ->join('attribute')
            ->where([
                'attribute.attributeGroupId' => $attributeGroupId,
                'classificationId'           => $classificationId
            ])
            ->find()
            ->toArray();

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                try {
                    $this->deleteEntity($attribute['id']);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('AttributeGroup hierarchical removing from Classification failed: ' . $e->getMessage());
                }
            }
        }

        return true;
    }

    protected function createPseudoTransactionDeleteJobs(string $id, $parentTransactionId = null): void
    {
        return;
        foreach ($this->getRepository()->getInheritedPavs($id) as $pav) {
            $parentId = $this->getPseudoTransactionManager()->pushDeleteEntityJob('ProductAttributeValue', $pav->get('id'));
            $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $pav->get('productId'), null, $parentId);
        }

        if($this->getMetadata()->get(['scopes','Classification','type']) === 'Hierarchy'){
            parent::createPseudoTransactionDeleteJobs($id, $parentTransactionId);
        }
    }
}
