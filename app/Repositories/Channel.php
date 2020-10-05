<?php

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Channel
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Channel extends Base
{
    /**
     * @param string $categoryRootId
     * @param Entity $channel
     * @param bool   $unrelate
     *
     * @return bool
     * @throws \Espo\Core\Exceptions\Error
     */
    public function cascadeProductsRelating(string $categoryRootId, Entity $channel, bool $unrelate = false): bool
    {
        $categoryRoot = $this->getEntityManager()->getEntity('Category', $categoryRootId);
        if (empty($categoryRoot)) {
            return false;
        }

        /** @var Product $productRepository */
        $productRepository = $this->getEntityManager()->getRepository('Product');

        // find products
        $products = $productRepository
            ->distinct()
            ->select(['id'])
            ->join('categories')
            ->where(['categories.id' => array_column($categoryRoot->getChildren()->toArray(), 'id')])
            ->find();

        foreach ($products as $product) {
            if ($unrelate) {
                $productRepository->unrelate($product, 'channels', $channel);
            } else {
                $productRepository->relate($product, 'channels', $channel);

                // set from_category_tree param
                $this
                    ->getEntityManager()
                    ->nativeQuery(
                        "UPDATE product_channel SET from_category_tree=1 WHERE product_id=:productId AND channel_id=:channelId AND deleted=0",
                        ['productId' => $product->get('id'), 'channelId' => $channel->get('id')]
                    );
            }
        }

        return true;
    }
}
