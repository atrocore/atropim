<?php

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class AttributeController
 *
 * @author r.ratsun@gmail.com
 */
class AttributeController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeActionDelete(Event $event)
    {
        // get data
        $arguments = $event->getArguments();

        if (empty($arguments['data']->force) && !empty($arguments['params']['id'])) {
            $this->validRelationsWithProduct([$arguments['params']['id']]);
            $this->validRelationsWithProductFamilies([$arguments['params']['id']]);
        }
    }

    /**
     * @param Event $event
     */
    public function beforeActionMassDelete(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        if (empty($data->force) && !empty($data->ids)) {
            $this->validRelationsWithProduct($data->ids);
            $this->validRelationsWithProductFamilies($data->ids);
        }
    }

    /**
     * @param array $idsAttribute
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProductFamilies(array $idsAttribute): void
    {
        if ($this->hasProductFamilies($idsAttribute)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Attribute is used in product families. Please, update product families first',
                    'exceptions',
                    'Attribute'
                )
            );
        }
    }

    /**
     * @param array $idsAttribute
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProduct(array $idsAttribute): void
    {
        if ($this->hasProduct($idsAttribute)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Attribute is used in products. Please, update products first',
                    'exceptions',
                    'Attribute'
                )
            );
        }
    }

    /**
     * Is attribute used in products
     *
     * @param array $idsAttribute
     *
     * @return bool
     */
    protected function hasProduct(array $idsAttribute): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['attributeId' => $idsAttribute])
            ->count();

        return !empty($count);
    }

    /**
     * Is attribute used in Product Families
     *
     * @param array $idsAttribute
     *
     * @return bool
     */
    protected function hasProductFamilies(array $idsAttribute): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->where(['attributeId' => $idsAttribute])
            ->count();

        return !empty($count);
    }
}
