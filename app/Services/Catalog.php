<?php
declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

/**
 * Catalog service
 *
 * @author r.ratsun@gmail.com
 */
class Catalog extends Base
{
    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // get products count
        $productsCount = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->where(['catalogId' => $entity->get('id')])
            ->count();

        // set products count to entity
        $entity->set('productsCount', (int)$productsCount);
    }

    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateProducts(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($products = $duplicatingEntity->get('products'))) {
            // get language
            $language = $this->getInjection('language');

            foreach ($products as $product) {
                if (!in_array($product->get('type'), array_keys($this->getMetadata()->get('pim.productType', [])))) {
                    continue 1;
                }

                // prepare name
                $name = sprintf(
                    $language->translate("Create product '%s' for catalog '%s'", "queueManager", "Catalog"),
                    $product->get('name'),
                    $entity->get('name')
                );

                // prepare data
                $data = [
                    'productId' => $product->get('id'),
                    'catalogId' => $entity->get('id')
                ];

                $this
                    ->getInjection('queueManager')
                    ->push($name, 'QueueManagerDuplicateProduct', $data);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('queueManager');
        $this->addDependency('language');
    }
}
