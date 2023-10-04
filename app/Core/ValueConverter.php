<?php
declare(strict_types=1);

namespace Pim\Core;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Injectable;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ValueConverter extends Injectable
{
    public function __construct()
    {
        $this->addDependency('entityManager');
    }

    public function convertTo(\stdClass $data, Entity $attribute): void
    {
        if (empty($attribute->get('type'))) {
            throw new BadRequest('Attribute is invalid. There is no attribute type.');
        }

        /**
         * Convert unit to unitId for backward compatibility
         */
        if (property_exists($data, 'valueUnit') && !property_exists($data, 'valueUnitId')) {
            $units = $this->getMeasureUnits($attribute->get('measureId'));
            foreach ($units as $unit) {
                if ($unit->get('name') === $data->valueUnit) {
                    $data->valueUnitId = $unit->get('id');
                    break;
                }
            }
            unset($data->valueUnit);
        }

        /**
         * Keep virtual values for conditions and validation
         */
        foreach (['value', 'valueUnitId', 'valueId', 'valueFrom', 'valueTo', 'valueCurrency'] as $name) {
            if (property_exists($data, $name)) {
                $data->_virtualValue[$name] = $data->$name;
            }
        }

        switch ($attribute->get('type')) {
            case 'extensibleEnum':
                if (property_exists($data, 'value')) {
                    $data->varcharValue = null;
                    $option = $this->findExtensibleEnumOption($attribute->get('extensibleEnumId'), $data->value);
                    if (!empty($option)) {
                        $data->varcharValue = $option->get('id');
                    }
                    unset($data->value);
                }
                break;
            case 'extensibleMultiEnum':
                if (property_exists($data, 'value')) {
                    $data->textValue = [];
                    $values = [];
                    $inputValue = $data->value;
                    if (!is_array($inputValue)) {
                        $inputValue = @json_decode((string)$inputValue, true);
                    }
                    if (!empty($inputValue)) {
                        foreach ($inputValue as $val) {
                            $option = $this->findExtensibleEnumOption($attribute->get('extensibleEnumId'), $val);
                            $values[] = !empty($option) ? $option->get('id') : $val;
                        }
                    }

                    $data->textValue = json_encode($values);
                    unset($data->value);
                }
                break;
            case 'array':
            case 'text':
            case 'wysiwyg':
                if (property_exists($data, 'value')) {
                    $data->textValue = $data->value;
                    unset($data->value);
                }
                break;
            case 'bool':
                if (property_exists($data, 'value')) {
                    $data->boolValue = !empty($data->value);
                    unset($data->value);
                }
                break;
            case 'int':
                if (property_exists($data, 'value')) {
                    $data->intValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->varcharValue = $data->valueUnitId;
                    unset($data->valueUnitId);
                }
                break;
            case 'rangeInt':
                if (property_exists($data, 'valueFrom')) {
                    $data->intValue = $data->valueFrom;
                    unset($data->valueFrom);
                }
                if (property_exists($data, 'valueTo')) {
                    $data->intValue1 = $data->valueTo;
                    unset($data->valueTo);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->varcharValue = $data->valueUnitId;
                    unset($data->valueUnitId);
                }
                break;
            case 'currency':
                if (property_exists($data, 'value')) {
                    $data->floatValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'data') && property_exists($data->data, 'currency')) {
                    $data->varcharValue = $data->data->currency;
                }
                if (property_exists($data, 'valueCurrency')) {
                    $data->varcharValue = $data->valueCurrency;
                    unset($data->valueCurrency);
                }
                break;
            case 'float':
                if (property_exists($data, 'value')) {
                    $data->floatValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->varcharValue = $data->valueUnitId;
                    unset($data->valueUnitId);
                }
                break;
            case 'rangeFloat':
                if (property_exists($data, 'valueFrom')) {
                    $data->floatValue = $data->valueFrom;
                    unset($data->valueFrom);
                }
                if (property_exists($data, 'valueTo')) {
                    $data->floatValue1 = $data->valueTo;
                    unset($data->valueTo);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->varcharValue = $data->valueUnitId;
                    unset($data->valueUnitId);
                }
                break;
            case 'date':
                if (property_exists($data, 'value')) {
                    $data->dateValue = $data->value;
                    unset($data->value);
                }
                break;
            case 'datetime':
                if (property_exists($data, 'value')) {
                    $data->datetimeValue = $data->value;
                    unset($data->value);
                }
                break;
            case 'asset':
            case 'link':
                if (property_exists($data, 'value')) {
                    $data->varcharValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueId')) {
                    $data->varcharValue = $data->valueId;
                    unset($data->value);
                }
                break;
            default:
                if (property_exists($data, 'value')) {
                    $data->varcharValue = $data->value;
                    unset($data->value);
                }
                break;
        }

        foreach (['valueName', 'valueNames', 'valueOptionData', 'valueOptionsData', 'valueAllUnits', 'valuePathsData'] as $name) {
            if (property_exists($data, $name)) {
                unset($data->$name);
            }
        }
    }

    public function convertFrom(Entity $entity, Entity $attribute, bool $clear = true): void
    {
        $entity->set('attributeType', $attribute->get('type'));

        switch ($attribute->get('type')) {
            case 'rangeInt':
                if ($entity->has('intValue')) {
                    $entity->set('valueFrom', $entity->get('intValue'));
                    $entity->set('valueTo', $entity->get('intValue1'));
                    $entity->set('valueUnitId', $entity->get('varcharValue'));
                }
                break;
            case 'rangeFloat':
                if ($entity->has('floatValue')) {
                    $entity->set('valueFrom', $entity->get('floatValue'));
                    $entity->set('valueTo', $entity->get('floatValue1'));
                    $entity->set('valueUnitId', $entity->get('varcharValue'));
                }
                break;
            case 'array':
                if ($entity->has('textValue')) {
                    $entity->set('value', @json_decode((string)$entity->get('textValue'), true));
                }
                break;
            case 'extensibleMultiEnum':
                $entity->set('attributeExtensibleEnumId', $attribute->get('extensibleEnumId'));
                if ($entity->has('textValue')) {
                    $entity->set('value', @json_decode((string)$entity->get('textValue'), true));
                    $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->getPreparedOptions($entity->get('attributeExtensibleEnumId'), $entity->get('value'));
                    if (isset($options[0])) {
                        $entity->set('valueNames', array_column($options, 'preparedName', 'id'));
                        $entity->set('valueOptionsData', $options);
                    }
                }
                break;
            case 'extensibleEnum':
                $entity->set('attributeExtensibleEnumId', $attribute->get('extensibleEnumId'));
                if ($entity->has('varcharValue')) {
                    $entity->set('value', $entity->get('varcharValue'));
                    $option = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->getPreparedOption($entity->get('attributeExtensibleEnumId'), $entity->get('value'));
                    if (!empty($option)) {
                        $entity->set('valueName', $option['preparedName']);
                        $entity->set('valueOptionData', $option);
                    }
                }
                break;
            case 'text':
            case 'wysiwyg':
                if ($entity->has('textValue')) {
                    $entity->set('value', $entity->get('textValue'));
                }
                break;
            case 'bool':
                if ($entity->has('boolValue')) {
                    $entity->set('value', !empty($entity->get('boolValue')));
                }
                break;
            case 'currency':
                if ($entity->has('floatValue')) {
                    $entity->set('value', $entity->get('floatValue'));
                    $entity->set('valueCurrency', $entity->get('varcharValue'));
                }
                break;
            case 'int':
                if ($entity->has('intValue')) {
                    $entity->set('value', $entity->get('intValue'));
                    $entity->set('valueUnitId', $entity->get('varcharValue'));
                }
                break;
            case 'float':
                if ($entity->has('floatValue')) {
                    $entity->set('value', $entity->get('floatValue'));
                    $entity->set('valueUnitId', $entity->get('varcharValue'));
                }
                break;
            case 'date':
                if ($entity->has('dateValue')) {
                    $entity->set('value', $entity->get('dateValue'));
                }
                break;
            case 'datetime':
                if ($entity->has('datetimeValue')) {
                    $entity->set('value', $entity->get('datetimeValue'));
                }
                break;
            case 'link':
                if ($entity->has('varcharValue')) {
                    $entity->set('valueId', $entity->get('varcharValue'));
                    if (!empty($entity->get('valueId'))) {
                        $foreign = $this->getEntityManager()->getEntity($attribute->get('entityType'), $entity->get('valueId'));
                        if (!empty($foreign)) {
                            $entity->set('valueName', $foreign->get($attribute->get('foreignField') ?? 'name'));
                        }
                    }
                }
                break;
            case 'asset':
                if ($entity->has('varcharValue')) {
                    $entity->set('value', $entity->get('varcharValue'));
                    $entity->set('valueId', $entity->get('varcharValue'));
                    if (!empty($entity->get('valueId'))) {
                        if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get('valueId')))) {
                            $entity->set('valueName', $attachment->get('name'));
                            $entity->set('valuePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment));
                        }
                    }
                }
                break;
            default:
                if ($entity->has('varcharValue')) {
                    $entity->set('value', $entity->get('varcharValue'));
                }
                break;
        }

        if ($clear) {
            $entity->clear('boolValue');
            $entity->clear('dateValue');
            $entity->clear('datetimeValue');
            $entity->clear('intValue');
            $entity->clear('intValue1');
            $entity->clear('floatValue');
            $entity->clear('floatValue1');
            $entity->clear('varcharValue');
            $entity->clear('textValue');
        }
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    protected function getMeasureUnits(string $measureId): array
    {
        return $this->getEntityManager()->getRepository('Measure')->getMeasureUnits($measureId);
    }

    protected function findExtensibleEnumOption(string $extensibleEnumId, $value)
    {
        if (empty($value)) {
            return null;
        }

        return $this->getEntityManager()->getRepository('ExtensibleEnumOption')
            ->where([
                'extensibleEnumId' => $extensibleEnumId,
                'OR'               => [
                    ['id' => $value],
                    ['code' => $value],
                ]
            ])
            ->findOne();
    }
}
