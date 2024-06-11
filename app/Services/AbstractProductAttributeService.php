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

use Atro\Core\Templates\Services\Base;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Core\ValueConverter;

class AbstractProductAttributeService extends Base
{
    protected $foreignEntity;
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
        $this->addDependency(ValueConverter::class);
    }
    protected function prepareDefaultLanguages(\stdClass $attachment): void
    {
        if (
            !property_exists($attachment, 'language')
            && !property_exists($attachment, 'languages')
            && property_exists($attachment, 'attributeId')
            && !empty($attribute = $this->getEntityManager()->getEntity('Attribute', $attachment->attributeId))
            && $attribute->get('isMultilang')
        ) {
            $attachment->languages = array_merge($this->getConfig()->get('inputLanguageList', []), ['main']);
        }
    }

    protected function multipleCreateViaLanguages(\stdClass $attachment)
    {
        if (property_exists($attachment, 'channelId') && !empty($channel = $this->getEntityManager()->getEntity('Channel', $attachment->channelId))) {
            if (!empty($channel->get('locales'))) {
                $attachment->languages = array_intersect($attachment->languages, $channel->get('locales'));
            }
        }

        if (empty($attachment->languages)) {
            throw new BadRequest('There is no available language in selected channel that correspond to selected ones.');
        }

        foreach ($attachment->languages as $language) {
            $attach = clone $attachment;
            unset($attach->languages);
            $attach->language = $language;

            try {
                $entity = $this->createEntity($attach);
                $result = $entity;
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('MultipleCreateViaLanguages: ' . $e->getMessage());
            }
        }

        if (empty($result)) {
            throw $e;
        }

        return $result;
    }

    protected function getAttributeViaInputData(\stdClass $data, ?string $id = null): Entity
    {
        if (property_exists($data, 'attributeId')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($data->attributeId);
        } elseif (!empty($id) && !empty($entity = $this->getRepository()->get($id))) {
            $attribute = $entity->get('attribute');
        }

        if (empty($attribute)) {
            throw new BadRequest('Attribute is required.');
        }

        return $attribute;
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {

        $foreignFieldId = strtolower($this->foreignEntity).'Id';

        if (!property_exists($data, $foreignFieldId)) {
            return;
        }

        $children = $this->getEntityManager()->getRepository($this->foreignEntity)->getChildrenArray($data->$foreignFieldId);
        foreach ($children as $child) {
            $inputData = clone $data;
            $inputData->productId = $child['id'];
            $inputData->productName = $child['name'];
            $transactionId = $this->getPseudoTransactionManager()->pushCreateEntityJob($this->entityType, $inputData, $parentTransactionId);
            $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->foreignEntity, $inputData->$foreignFieldId, null, $transactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionCreateJobs(clone $inputData, $transactionId);
            }
        }
    }

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data, string $parentTransactionId = null): void
    {
        $foreignFieldId = strtolower($this->foreignEntity).'Id';
        $children = $this->getRepository()->getChildrenArray($id);

        $pav1 = $this->getRepository()->get($id);
        foreach ($children as $child) {
            $pav2 = $this->getRepository()->get($child['id']);

            $inputData = new \stdClass();
            if ($this->getRepository()->arePavsValuesEqual($pav1, $pav2)) {
                foreach (['value', 'valueUnitId', 'valueCurrency', 'valueFrom', 'valueTo', 'valueId', 'channelId', 'isRequired'] as $key) {
                    if (property_exists($data, $key)) {
                        $inputData->$key = $data->$key;
                    }
                }
            }

            if (property_exists($data, 'isVariantSpecificAttribute')) {
                $inputData->isVariantSpecificAttribute = $data->isVariantSpecificAttribute;
            }

            if (!empty((array)$inputData)) {
                if (in_array($pav1->get('attributeType'), ['extensibleMultiEnum', 'array']) && property_exists($inputData, 'value') && is_string($inputData->value)) {
                    $inputData->value = @json_decode($inputData->value, true);
                }
                $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $child['id'], $inputData, $parentTransactionId);
                $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->foreignEntity, $pav2->get($foreignFieldId), null, $transactionId);
                if ($child['childrenCount'] > 0) {
                    $this->createPseudoTransactionUpdateJobs($child['id'], clone $inputData, $transactionId);
                }
            }
        }
    }

    protected function createPseudoTransactionDeleteJobs(string $id, string $parentTransactionId = null): void
    {
        $foreignFieldId = strtolower($this->foreignEntity).'Id';
        $children = $this->getRepository()->getChildrenArray($id);
        foreach ($children as $child) {
            $transactionId = $this->getPseudoTransactionManager()->pushDeleteEntityJob($this->entityType, $child['id'], $parentTransactionId);
            if (!empty($childPav = $this->getRepository()->get($child['id']))) {
                $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->foreignEntity, $childPav->get($foreignFieldId), null, $transactionId);
            }
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionDeleteJobs($child['id'], $transactionId);
            }
        }
    }

    public function inheritPav($pav): bool
    {
        if (is_string($pav)) {
            $pav = $this->getEntity($pav);
        }

        if (!($pav instanceof \Pim\Entities\AbstractAttributeValue)) {
            return false;
        }

        $parentPav = $this->getRepository()->getParentPav($pav);
        if (empty($parentPav)) {
            return false;
        }

        $this->getInjection(ValueConverter::class)->convertFrom($parentPav, $parentPav->get('attribute'));

        $input = new \stdClass();
        $input->isVariantSpecificAttribute = $parentPav->get('isVariantSpecificAttribute');
        $input->isRequired = $parentPav->get('isRequired');
        foreach ($parentPav->toArray() as $name => $v) {
            if (substr($name, 0, 5) === 'value') {
                $input->$name = $v;
            }
        }

        $this->updateEntity($pav->get('id'), $input);

        return true;
    }
}
