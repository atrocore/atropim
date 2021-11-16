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

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Services\QueueManagerBase;

class QueueManagerChannel extends QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        if (empty($data['action'])) {
            return false;
        }

        return $this->{$data['action']}($data);
    }

    protected function updateCategory(array $data): bool
    {
        $channel = $this->getEntityManager()->getEntity('Channel', $data['channelId']);
        if (empty($channel)) {
            return false;
        }

        if (!empty($data['fetchedCategoryId'])) {
            $prevRoot = $this->getEntityManager()->getEntity('Category', $data['fetchedCategoryId']);
            if (empty($prevRoot)) {
                throw new BadRequest("No such category '{$data['fetchedCategoryId']}'.");
            }

            foreach ($prevRoot->getTreeProducts() as $product) {
                $this->getEntityManager()->getRepository('Product')->unrelateChannel($product, $channel);
            }
        }

        if (!empty($data['categoryId'])) {
            $root = $this->getEntityManager()->getEntity('Category', $data['categoryId']);
            if (empty($root)) {
                throw new BadRequest("No such category '{$data['categoryId']}'.");
            }
            foreach ($root->getTreeProducts() as $product) {
                $this->getEntityManager()->getRepository('Product')->relateChannel($product, $channel, true);
            }
        }

        return true;
    }
}
