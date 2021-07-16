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

declare(strict_types=1);

namespace Pim\Listeners;

use Pim\Repositories\Product;
use Treo\Core\EventManager\Event;

/**
 * Class ProductService
 */
class ProductService extends AbstractEntityListener
{
    /**
     * @param Event $event
     */
    public function beforeUpdateEntity(Event $event)
    {
        $data = $event->getArgument('data');

        if (!empty($data->_mainEntityId)) {
            $this
                ->getProductRepository()
                ->updateProductCategorySortOrder((string)$event->getArgument('id'), (string)$data->_mainEntityId, (int)$data->pcSorting, false);
        }

        if (!empty($data->_id)) {
            $this
                ->getProductRepository()
                ->updateProductCategorySortOrder((string)$event->getArgument('id'), (string)$data->_id, (int)$data->pcSorting);
        }
    }

    /**
     * @param Event $event
     */
    public function afterFindLinkedEntities(Event $event)
    {
        $result = $event->getArgument('result');

        if (empty($result['total'])){
            return;
        }

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');

        if ($link === 'channels') {
            $data = $this->getProductRepository()->getChannelRelationData($id);
            foreach ($result['collection'] as $channel) {
                $channel->set('isActiveForChannel', !empty($data[$channel->get('id')]['isActive']));
                $channel->set('isFromCategoryTree', !empty($data[$channel->get('id')]['isFromCategoryTree']));
            }
        }
    }

    /**
     * @return Product
     */
    protected function getProductRepository(): Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }
}
