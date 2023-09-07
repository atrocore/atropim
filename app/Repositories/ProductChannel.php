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

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;

class ProductChannel extends Relationship
{
    public function createRelationshipViaCategory(Entity $product, Entity $category): void
    {
        $channels = $category->get('channels');
        if (empty($channels) || count($channels) === 0) {
            return;
        }

        foreach ($channels as $channel) {
            $productChannel = $this->get();
            $productChannel->set(['productId' => $product->get('id'), 'channelId' => $channel->get('id')]);
            try {
                $this->getEntityManager()->saveEntity($productChannel);
            } catch (\PDOException $e) {
                if (empty($e->errorInfo[1]) || $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
            }
        }
    }

    public function deleteRelationshipViaCategory(Entity $product, Entity $category): void
    {
        $channels = $category->get('channels');
        if (empty($channels) || count($channels) === 0) {
            return;
        }

        $query = "SELECT DISTINCT c.id
                  FROM `channel` c
                  LEFT JOIN `product_category` pc ON pc.product_id='{$product->get('id')}'
                  LEFT JOIN `category_channel` cc ON cc.channel_id=c.id AND pc.category_id=cc.category_id
                  WHERE pc.category_id!='{$category->get('id')}'
                    AND pc.deleted=0
                    AND cc.deleted=0
                    AND c.deleted=0";

        $channelsIds = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($channels as $channel) {
            if (in_array($channel->get('id'), $channelsIds)) {
                continue 1;
            }
            $this->where(['productId' => $product->get('id'), 'channelId' => $channel->get('id')])->removeCollection();
        }
    }
}
