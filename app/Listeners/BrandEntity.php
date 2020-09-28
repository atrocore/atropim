<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Services\Product;
use Treo\Core\EventManager\Event;

/**
 * Class BrandEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class BrandEntity extends AbstractEntityListener
{
    /**
     * Before save action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        if (!$this->isCodeValid($event->getArgument('entity'))) {
            throw new BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
        }
    }

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
