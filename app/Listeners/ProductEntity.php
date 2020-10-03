<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;
use Pim\Entities\Channel;

/**
 * Class ProductEntity
 *
 * @package Pim\Listeners
 * @author  r.ratsun@gmail.com
 */
class ProductEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // is sku valid
        if (!$this->isSkuUnique($entity)) {
            throw new BadRequest($this->exception('Product with such SKU already exist'));
        }

        if ($entity->isAttributeChanged('catalogId')) {
            // is product categories in selected catalog
            $this->isProductCategoriesInSelectedCatalog($entity);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->exception('You can\'t change field of Type'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // get options
        $options = $event->getArgument('options');

        $skipUpdate = empty($entity->skipUpdateProductAttributesByProductFamily) && empty($options['skipProductFamilyHook']);

        if ($skipUpdate && empty($entity->isDuplicate)) {
            $this->updateProductAttributesByProductFamily($entity, $options);
        }
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        //set default value in isActive for channel after deleted link
        if ($event->getArgument('relationName') == 'channels' && $event->getArgument('foreign') instanceof Channel) {
            $dataEntity = new \StdClass();
            $dataEntity->entityName = 'Product';
            $dataEntity->entityId = $event->getArgument('entity')->get('id');
            $dataEntity->value = (int)!empty(
            $event
                ->getArgument('entity')
                ->getRelations()['channels']['additionalColumns']['isActive']['default']
            );

            $this
                ->getService('Channel')
                ->setIsActiveEntity($event->getArgument('foreign')->get('id'), $dataEntity, true);
        }
    }

    /**
     * Before action delete
     *
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        $id = $event->getArgument('entity')->id;
        $this->removeProductAttributeValue($id);
    }

    /**
     * @param string $id
     */
    protected function removeProductAttributeValue(string $id)
    {
        $productAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $id])
            ->find();

        foreach ($productAttributes as $attr) {
            $this->getEntityManager()->removeEntity($attr, ['skipProductAttributeValueHook' => true]);
        }
    }

    /**
     * @param Entity $product
     * @param string $field
     *
     * @return bool
     */
    protected function isSkuUnique(Entity $product): bool
    {
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['sku' => $product->get('sku'), 'catalogId' => $product->get('catalogId')])
            ->find();

        if (count($products) > 0) {
            foreach ($products as $item) {
                if ($item->get('id') != $product->get('id')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isProductCategoriesInSelectedCatalog(Entity $entity): bool
    {
        // @todo fix it

        return false;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return bool
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function updateProductAttributesByProductFamily(Entity $entity, array $options): bool
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('productFamilyId')) {
            // unlink attributes from old product family
            $this
                ->getEntityManager()
                ->nativeQuery(
                    "UPDATE product_attribute_value SET product_family_attribute_id=NULL WHERE product_id=:productId AND product_family_attribute_id IS NOT NULL AND deleted=0",
                    ['productId' => $entity->get('id')]
                );
        }

        if (empty($productFamily = $entity->get('productFamily'))) {
            return true;
        }

        // get product family attributes
        $productFamilyAttributes = $productFamily->get('productFamilyAttributes');

        if (count($productFamilyAttributes) > 0) {
            /** @var \Pim\Repositories\ProductAttributeValue $repository */
            $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');

            foreach ($productFamilyAttributes as $productFamilyAttribute) {
                // create
                $productAttributeValue = $repository->get();
                $productAttributeValue->set(
                    [
                        'productId'                => $entity->get('id'),
                        'attributeId'              => $productFamilyAttribute->get('attributeId'),
                        'productFamilyAttributeId' => $productFamilyAttribute->get('id'),
                        'isRequired'               => $productFamilyAttribute->get('isRequired'),
                        'scope'                    => $productFamilyAttribute->get('scope')
                    ]
                );

                // relate channels if it needs
                if ($productFamilyAttribute->get('scope') == 'Channel') {
                    $channels = $productFamilyAttribute->get('channels');
                    if (count($channels) > 0) {
                        $productAttributeValue->set('channelsIds', array_column($channels->toArray(), 'id'));
                    }
                }

                // save
                try {
                    $this->getEntityManager()->saveEntity($productAttributeValue);
                } catch (BadRequest $e) {
                    $message = sprintf('Such product attribute \'%s\' already exists', $productFamilyAttribute->get('attribute')->get('name'));
                    if ($message == $e->getMessage()) {
                        $copy = $repository->findCopy($productAttributeValue);
                        $copy->set('productFamilyAttributeId', $productFamilyAttribute->get('id'));
                        $copy->set('isRequired', $productAttributeValue->get('isRequired'));

                        if ($productFamilyAttribute->get('scope') == 'Channel') {
                            $copy->set('channelsIds', $productAttributeValue->get('channelsIds'));
                        }

                        $copy->skipPfValidation = true;

                        $this->getEntityManager()->saveEntity($copy);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Product');
    }
}
