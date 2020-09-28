<?php

declare(strict_types=1);

namespace Pim\Import\Types\Simple\FieldConverters;

use Espo\Core\Exceptions\Error;
use Treo\Core\Utils\Util;

/**
 * Class EnumMultiLang
 *
 * @author r.ratsun@gmail.com
 */
class EnumMultiLang extends \Import\Types\Simple\FieldConverters\AbstractConverter
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter)
    {
        $value = (is_null($config['column']) || $row[$config['column']] == '') ? $config['default'] : $row[$config['column']];
        $inputRow->{$config['name']} = $value;

        if (isset($config['attributeId'])) {
            $attribute = $config['attribute'];

            $typeValue = $attribute->get('typeValue');
            $key = array_search($value, $typeValue);

            if ($key !== false) {
                foreach ($this->container->get('config')->get('inputLanguageList', []) as $locale) {
                    $locale = ucfirst(Util::toCamelCase(strtolower($locale)));

                    $inputRow->{$config['name'] . $locale} = $attribute->get('typeValue' . $locale)[$key];
                }
            } else {
                throw new Error("Not found any values for attribute '{$attribute->get('name')}'");
            }
        }
    }
}