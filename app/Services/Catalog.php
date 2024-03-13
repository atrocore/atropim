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

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Catalog extends Base
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('productsCount', $this->getRepository()->getProductsCount($entity));
    }

    protected function duplicateProducts(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($products = $duplicatingEntity->get('products'))) {
            // get language
            $language = $this->getInjection('language');

            foreach ($products as $product) {
                // prepare name
                $name = sprintf(
                    $language->translate("createProductForCatalog", "queueManager", "Catalog"),
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

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('language');
    }
}
