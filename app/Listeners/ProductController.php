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
            $isActives = $this->getIsActive($params['id']);
            foreach ($result['list'] as &$item) {
                $item->isActiveEntity = (bool)$isActives[$item->id]['isActive'];
            }
        }

        $event->setArgument('result', $result);
    }

    /**
     * @param string $productId
     * @return array
     */
    protected function getIsActive(string $productId): array
    {
        return $this
            ->getEntityManager()
            ->nativeQuery("SELECT channel_id, is_active AS isActive FROM product_channel pc WHERE product_id = '{$productId}' AND pc.deleted = 0")
            ->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
    }
}
