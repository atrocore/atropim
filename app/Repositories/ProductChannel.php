<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
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

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $exist = $this
                ->where([
                    'productId' => $entity->get('productId'),
                    'channelId' => $entity->get('channelId'),
                    'id!=' => $entity->id
                ])
                ->findOne();

            if (!empty($exist)) {
                throw new BadRequest($this->getLanguage()->translate('productChannelAlreadyExist', 'exceptions', 'ProductChannel'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('Product')->relatePfas($entity->get('product'), $entity->get('channel'));

        parent::afterSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getRepository('Product')->unrelatePfas($entity->get('product'), $entity->get('channel'));
        $this
            ->getEntityManager()
            ->getRepository('ProductAsset')
            ->where([
                'channelId' => $entity->get('channelId'),
                'productId' => $entity->get('productId')
            ])
            ->removeCollection();

        parent::afterRemove($entity, $options);
    }
}
