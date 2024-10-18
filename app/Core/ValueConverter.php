<?php
declare(strict_types=1);

namespace Pim\Core;

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Entities\File;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Injectable;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ValueConverter extends Injectable
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency('memoryStorage');
        $this->addDependency('twig');
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
        foreach (['value', 'valueUnitId', 'valueId', 'valueFrom', 'valueTo', 'valueCurrency', 'valueIds'] as $name) {
            if (property_exists($data, $name)) {
                $data->_virtualValue[$name] = $data->$name;
            }
        }

        switch ($attribute->get('type')) {
            case 'extensibleEnum':
                if (property_exists($data, 'value')) {
                    $data->referenceValue = null;
                    $option = $this->findExtensibleEnumOption($attribute->get('extensibleEnumId'), $data->value);
                    if (!empty($option)) {
                        $data->referenceValue = $option->get('id');
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
            case 'markdown':
            case 'wysiwyg':
                if (property_exists($data, 'value')) {
                    $data->textValue = $data->value;
                    unset($data->value);
                }
                break;
            case 'bool':
                if (property_exists($data, 'value')) {
                    if(empty($attribute->get('notNull')) && $data->value === null){
                        $data->boolValue = null;
                    }else{
                        $data->boolValue = !empty($data->value);
                    }
                    unset($data->value);
                }
                break;
            case 'int':
                if (property_exists($data, 'value')) {
                    $data->intValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->referenceValue = $data->valueUnitId;
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
                    $data->referenceValue = $data->valueUnitId;
                    unset($data->valueUnitId);
                }
                break;
            case 'float':
                if (property_exists($data, 'value')) {
                    $data->floatValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueUnitId')) {
                    $data->referenceValue = $data->valueUnitId;
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
                    $data->referenceValue = $data->valueUnitId;
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
            case 'file':
            case 'link':
                if (property_exists($data, 'value')) {
                    $data->referenceValue = $data->value;
                    unset($data->value);
                }
                if (property_exists($data, 'valueId')) {
                    $data->referenceValue = $data->valueId;
                    unset($data->valueId);
                }
                break;
            case 'linkMultiple':
                // nothing to change
                break;
            case 'varchar':
                if (property_exists($data, 'value')) {
                    if(!empty($attribute->get('notNull')) && $data->value === null ){
                        $data->varcharValue = "";
                    }else{
                        $data->varcharValue = $data->value;
                    }
                    unset($data->value);
                } else {
                    if (empty($data->varcharValue) && !empty($default = $attribute->get('defaultValue'))) {
                        if (strpos($default, '{{') >= 0 && strpos($default, '}}') >= 0) {
                            // use twig
                            $default = $this->getInjection('twig')->renderTemplate($default, []);
                        }
                        $data->varcharValue = $default;
                    }
                }

                if (property_exists($data, 'valueUnitId')) {
                    $data->referenceValue = $data->valueUnitId;
                    unset($data->valueUnitId);
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
                    $entity->set('valueUnitId', $entity->get('referenceValue'));
                }
                break;
            case 'rangeFloat':
                if ($entity->has('floatValue')) {
                    $entity->set('valueFrom', $entity->get('floatValue'));
                    $entity->set('valueTo', $entity->get('floatValue1'));
                    $entity->set('valueUnitId', $entity->get('referenceValue'));
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
                    if (!$this->isExport()) {
                        $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                            ->getPreparedOptions($entity->get('attributeExtensibleEnumId'), $entity->get('value'));
                        if (isset($options[0])) {
                            $entity->set('valueNames', array_column($options, 'preparedName', 'id'));
                            $entity->set('valueOptionsData', $options);
                        }
                    }
                }
                break;
            case 'extensibleEnum':
                $entity->set('attributeExtensibleEnumId', $attribute->get('extensibleEnumId'));
                if ($entity->has('referenceValue')) {
                    $entity->set('value', $entity->get('referenceValue'));
                    if (!$this->isExport()) {
                        $option = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                            ->getPreparedOption($entity->get('attributeExtensibleEnumId'), $entity->get('value'));
                        if (!empty($option)) {
                            $entity->set('valueName', $option['preparedName']);
                            $entity->set('valueOptionData', $option);
                        }
                    }
                }
                break;
            case 'markdown':
            case 'text':
            case 'wysiwyg':
                if ($entity->has('textValue')) {
                    $entity->set('value', $entity->get('textValue'));
                }
                break;
            case 'bool':
                if ($entity->has('boolValue')) {
                    $entity->set('value', $entity->get('boolValue') === null ? null : !empty($entity->get('boolValue')));
                }
                break;
            case 'int':
                if ($entity->has('intValue')) {
                    $entity->set('value', $entity->get('intValue'));
                    $entity->set('valueUnitId', $entity->get('referenceValue'));
                }
                break;
            case 'float':
                if ($entity->has('floatValue')) {
                    $entity->set('value', $entity->get('floatValue'));
                    $entity->set('valueUnitId', $entity->get('referenceValue'));
                }
                break;
            case 'date':
                if ($entity->has('dateValue')) {
                    $value = $entity->get('dateValue');
                    if (!empty($value) && !empty($defaultDate = $attribute->get('defaultDate'))) {
                        $value = $this->getEntityManager()->getRepository('Attribute')->convertDateWithModifier($value, $defaultDate);
                    }
                    $entity->set('value', $value);
                }
                break;
            case 'datetime':
                if ($entity->has('datetimeValue')) {
                    $value = $entity->get('datetimeValue');
                    if (!empty($value) && !empty($defaultDate = $attribute->get('defaultDate'))) {
                        $value = $this->getEntityManager()->getRepository('Attribute')->convertDateWithModifier($value, $defaultDate, 'Y-m-d H:i:s');
                    }
                    $entity->set('value', $value);
                }
                break;
            case 'link':
                if ($entity->has('referenceValue')) {
                    $entity->set('valueId', $entity->get('referenceValue'));
                    if (!$this->isExport() && !empty($entity->get('valueId'))) {
                        $foreign = $this->getEntityManager()->getEntity($attribute->get('entityType'), $entity->get('valueId'));
                        if (!empty($foreign)) {
                            $entity->set('valueName', $foreign->get($attribute->get('entityField') ?? 'name'));
                        }
                    }
                }
                break;
            case 'linkMultiple':
                $field = $attribute->getLinkMultipleLinkName();
                if (!$this->isExport()) {
                    $column = $attribute->get('entityField');
                    $entity->loadLinkMultipleField($field);
                    $entity->set('valueIds', $entity->get($field . 'Ids'));
                    if ($column == 'name') {
                        $entity->set('valueNames', $entity->get($field . 'Names'));
                    } else if ($column != 'id') {
                        $entities = $this->getEntityManager()->getRepository($attribute->get('entityType'))
                            ->select(['id', $attribute->get('entityField')])
                            ->where(['id' => $entity->get('valueIds')])->find();
                        $names = new \stdClass();
                        if (!empty($entities) && $entities->count() > 0) {
                            $entities = $entities->toArray();
                            $names = array_column($entities, $attribute->get('entityField'), 'id');
                        }
                        $entity->set('valueNames', $names);
                    }
                }
                break;
            case 'file':
                if ($entity->has('referenceValue')) {
                    $entity->set('value', $entity->get('referenceValue'));
                    $entity->set('valueId', $entity->get('referenceValue'));
                    if (!$this->isExport() && !empty($entity->get('valueId'))) {
                        /** @var File $file */
                        $file = $this->getEntityManager()->getEntity('File', $entity->get('valueId'));
                        if (!empty($file)) {
                            $entity->set('valueName', $file->get('name'));
                            $entity->set('valuePathsData', $file->getPathsData());
                        }
                    }
                }
                break;
            case 'varchar':
                if ($entity->has('varcharValue')) {
                    $entity->set('value', $entity->get('varcharValue'));
                    $entity->set('valueUnitId', $entity->get('referenceValue'));
                }
                break;
            default:
                if ($entity->has('varcharValue')) {
                    $entity->set('value', $entity->get('varcharValue'));
                }
                break;
        }

        if ($clear && empty($this->getMemoryStorage()->get('exportJobId'))) {
            $entity->_technicalFieldValues = [
                "boolValue" => $entity->get('boolValue'),
                "dateValue" => $entity->get('dateValue'),
                "datetimeValue" => $entity->get('datetimeValue'),
                "intValue" => $entity->get('intValue'),
                "intValue1" => $entity->get('intValue1'),
                "floatValue" => $entity->get('floatValue'),
                "floatValue1" => $entity->get('floatValue1'),
                "varcharValue" => $entity->get('varcharValue'),
                "referenceValue" => $entity->get('referenceValue'),
                "textValue" => $entity->get('textValue')
            ];
            $entity->clear('boolValue');
            $entity->clear('dateValue');
            $entity->clear('datetimeValue');
            $entity->clear('intValue');
            $entity->clear('intValue1');
            $entity->clear('floatValue');
            $entity->clear('floatValue1');
            $entity->clear('varcharValue');
            $entity->clear('referenceValue');
            $entity->clear('textValue');
        }
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }

    protected function isExport(): bool
    {
        return !empty($this->getEntityManager()->getMemoryStorage()->get('exportJobId'));
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

        $data = $this->getEntityManager()
            ->getConnection()
            ->createQueryBuilder()
            ->from('extensible_enum_option')
            ->select('*')
            ->where('id IN ( 
                SELECT extensible_enum_option_id 
                    FROM extensible_enum_extensible_enum_option 
                    WHERE extensible_enum_id=:extensibleEnumId AND deleted=:false
                )'
            )
            ->andWhere('id=:value OR code=:value')
            ->setParameter('extensibleEnumId',$extensibleEnumId, Mapper::getParameterType($extensibleEnumId))
            ->setParameter('false',false,Mapper::getParameterType(false))
            ->setParameter('value', $value, Mapper::getParameterType($value))
            ->fetchAssociative();

        if (!$data) {
            return null;
        }

        $option = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->get();

        $option->set(Util::arrayKeysToCamelCase($data));

        return $option;
    }
}
