<?php

declare(strict_types=1);

namespace Pim\Services;

use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Treo\Core\Utils\Util;

/**
 * ProductAttributeValue service
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductAttributeValue extends AbstractService
{
    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('isCustom', $this->isCustom($entity));
        $entity->set('attributeType', !empty($entity->get('attribute')) ? $entity->get('attribute')->get('type') : null);
        $entity->set('attributeIsMultilang', !empty($entity->get('attribute')) ? $entity->get('attribute')->get('isMultilang') : false);

        $this->convertValue($entity);
    }

    /**
     * @inheritdoc
     */
    public function updateEntity($id, $data)
    {
        // prepare data
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data->$k = Json::encode($v);
            }
        }

        return parent::updateEntity($id, $data);
    }

    /**
     * @param Entity $entity
     */
    protected function convertValue(Entity $entity)
    {
        $type = $entity->get('attributeType');

        if (!empty($type)) {
            switch ($type) {
                case 'array':
                    $entity->set('value', Json::decode($entity->get('value'), true));
                    break;
                case 'bool':
                    $entity->set('value', (bool)$entity->get('value'));
                    foreach ($this->getInputLanguageList() as $multiLangField) {
                        $entity->set($multiLangField, (bool)$entity->get($multiLangField));
                    }
                    break;
                case 'int':
                    $entity->set('value', (int)$entity->get('value'));
                    break;
                case 'unit':
                case 'float':
                    $entity->set('value', (float)$entity->get('value'));
                    break;
                case 'multiEnum':
                    $entity->set('value', Json::decode($entity->get('value'), true));
                    foreach ($this->getInputLanguageList() as $multiLangField) {
                        $entity->set($multiLangField, Json::decode($entity->get($multiLangField), true));
                    }
                    break;
            }
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
     * @param Entity $entity
     *
     * @return bool
     */
    private function isCustom(Entity $entity): bool
    {
        // prepare is custom field
        $isCustom = true;

        if (!empty($productFamilyAttribute = $entity->get('productFamilyAttribute'))
            && !empty($productFamilyAttribute->get('productFamily'))) {
            $isCustom = false;
        }

        return $isCustom;
    }
}
