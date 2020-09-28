<?php

declare(strict_types=1);

namespace Pim\Import\Types\Simple\FieldConverters;

use Espo\ORM\Entity;
use Import\Types\Simple\FieldConverters\Unit as DefaultUnit;

/**
 * Class Unit
 *
 * @author r.ratsun@gmail.com
 */
class Unit extends DefaultUnit
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter)
    {
        if (isset($config['attributeId'])) {
            // prepare values
            $value = (!empty($config['column']) && $row[$config['column']] != '') ? $row[$config['column']] : $config['default'];
            $unit = (!empty($config['columnUnit']) && $row[$config['columnUnit']] != '') ? $row[$config['columnUnit']] : $config['defaultUnit'];

            // validate unit float value
            if (!is_null($value) && filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                throw new \Exception("Incorrect value for attribute '{$config['attribute']->get('name')}'");
            }

            // validate measuring unit
            if (!$this->validateUnit($unit, $entityType, $config)) {
                throw new \Exception("Incorrect measuring unit for attribute '{$config['attribute']->get('name')}'");
            }

            // prepare input row for attribute
            $inputRow->{$config['name']} = (float)$value;
            $inputRow->data = (object)['unit' => $unit];
        } else {
            parent::convert($inputRow, $entityType, $config, $row, $delimiter);
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareValue(\stdClass $restore, Entity $entity, array $item)
    {
        parent::prepareValue($restore, $entity, $item);
        if (isset($item['attributeId'])) {
            // prepare restore row for attribute
            $restore->data = $entity->get('data');
            unset($restore->{$item['name'].'Unit'});
        }
    }

    /**
     * @inheritDoc
     */
    protected function getMeasure(string $entityType, array $config): string
    {
        if (!isset($config['attributeId'])) {
            return parent::getMeasure($entityType, $config);
        } else {
            return $config['attribute']->get('typeValue')[0];
        }
    }
}
