<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class ProductController
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductController extends AbstractEntityListener
{
    /**
     * @param Event $event
     */
    public function afterActionListLinked(Event $event)
    {
        $params = $event->getArgument('params');
        $result = $event->getArgument('result');

        if ($params['link'] === 'channels') {
            $data = $this->getEntityManager()->getRepository('Product')->getChannelRelationData($params['id']);
            foreach ($result['list'] as &$item) {
                $item->isActiveEntity = (bool)$data[$item->id]['isActive'];
                $item->isFromCategoryTree = (bool)$data[$item->id]['isFromCategoryTree'];
            }
        }

        $event->setArgument('result', $result);
    }
}
