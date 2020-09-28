<?php
declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

/**
 * Class ProductAttributeValue
 *
 * @author r.ratsun@gmail.com
 */
class ProductAttributeValue extends Base
{
    /**
     * @param string $productFamilyAttributeId
     */
    public function removeCollectionByProductFamilyAttribute(string $productFamilyAttributeId)
    {
        $this
            ->where(['productFamilyAttributeId' => $productFamilyAttributeId])
            ->removeCollection(['skipProductAttributeValueHook' => true]);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        // get attribute
        $attribute = $entity->get('attribute');

        // get fields
        $fields = $this->getMetadata()->get(['entityDefs', 'ProductAttributeValue', 'fields'], []);

        if ($attribute->get('type') == 'enum' && !empty($attribute->get('isMultilang')) && $entity->isAttributeChanged('value')) {
            // find key
            $key = array_search($entity->get('value'), $attribute->get('typeValue'));

            foreach ($fields as $mField => $mData) {
                if (isset($mData['multilangField']) && $mData['multilangField'] == 'value') {
                    $data = $attribute->get('type' . ucfirst($mField));
                    if (isset($data[$key])) {
                        $entity->set($mField, $data[$key]);
                    } else {
                        $entity->set($mField, $entity->get('value'));
                    }
                }
            }
        }

        if ($attribute->get('type') == 'multiEnum' && !empty($attribute->get('isMultilang')) && $entity->isAttributeChanged('value')) {
            $values = Json::decode($entity->get('value'), true);

            $keys = [];
            foreach ($values as $value) {
                $keys[] = array_search($value, $attribute->get('typeValue'));
            }

            foreach ($fields as $mField => $mData) {
                if (isset($mData['multilangField']) && $mData['multilangField'] == 'value') {
                    $data = $attribute->get('type' . ucfirst($mField));
                    $values = [];
                    foreach ($keys as $key) {
                        $values[] = isset($data[$key]) ? $data[$key] : null;
                    }
                    $entity->set($mField, Json::encode($values));
                }
            }
        }
    }

    /**
     * @param Entity $entity
     *
     * @return Entity|null
     */
    public function findCopy(Entity $entity): ?Entity
    {
        // prepare copy
        $copy = null;

        if ($entity->get('scope') == 'Global') {
            $copy = $this
                ->where(
                    [
                        'id!='        => $entity->get('id'),
                        'productId'   => $entity->get('productId'),
                        'attributeId' => $entity->get('attributeId'),
                        'scope'       => 'Global',
                    ]
                )
                ->findOne();
        }

        if ($entity->get('scope') == 'Channel') {
            $copy = $this
                ->distinct()
                ->join('channels')
                ->where(
                    [
                        'id!='        => $entity->get('id'),
                        'productId'   => $entity->get('productId'),
                        'attributeId' => $entity->get('attributeId'),
                        'scope'       => 'Channel',
                        'channels.id' => $entity->get('channelsIds'),
                    ]
                )
                ->findOne();
        }

        return $copy;
    }
}
