<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\ORM\Entity;
use Pim\Services\Product;

/**
 * Class BrandEntity
 */
class BrandEntity extends AbstractEntityListener
{
    /**
     * After save action
     *
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        $this->updateProductActivation($event->getArgument('entity'));
    }

    /**
     * Deactivate Product if Brand deactivated
     *
     * @param Entity $entity
     */
    protected function updateProductActivation(Entity $entity)
    {
        if ($entity->isAttributeChanged('isActive') && !$entity->get('isActive')) {
            // prepare condition for Product filter
            $params = [
                'where' => [
                    [
                        'type'      => 'equals',
                        'attribute' => 'brandId',
                        'value'     => $entity->get('id')
                    ],
                    [
                        'type'      => 'isTrue',
                        'attribute' => 'isActive'
                    ]
                ]
            ];

            $this->getProductService()->massUpdate(['isActive' => false], $params);
        }
    }

    /**
     * Create Product service
     *
     * @return Product
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function getProductService(): Product
    {
        return $this->getServiceFactory()->create('Product');
    }
}
