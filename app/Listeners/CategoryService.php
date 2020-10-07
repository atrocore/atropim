<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class CategoryService
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class CategoryService extends AbstractEntityListener
{
    /**
     * @param Event $event
     */
    public function afterFindLinkedEntities(Event $event)
    {
        $this->setSorting($event);
    }

    /**
     * @param Event $event
     */
    protected function setSorting(Event $event)
    {
        $result = $event->getArgument('result');
        if (!empty($result['total'])) {
            $categoryId = $event->getArgument('id');

            /** @var array $linkData */
            $linkData = $this
                ->getEntityManager()
                ->getRepository('Product')
                ->getProductCategoryLinkData(array_column($result['collection']->toArray(), 'id'), [$categoryId]);
            foreach ($result['collection'] as $product) {
                foreach ($linkData as $item) {
                    if ($item['category_id'] == $categoryId && $item['product_id'] == $product->get('id')) {
                        $product->set('pcSorting', (int)$item['sorting']);
                        continue 2;
                    }
                }
            }
        }
    }
}
