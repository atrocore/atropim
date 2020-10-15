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

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class ProductFamilyController
 *
 * @author rr@atrocore.com
 */
class ProductFamilyController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionListLinked(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if ($data['params']['link'] == 'productFamilyAttributes' && !empty($data['result']['list'])) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->where(['id' => array_column($data['result']['list'], 'attributeId')])
                ->find();

            if (count($attributes) > 0) {
                foreach ($attributes as $attribute) {
                    foreach ($data['result']['list'] as $key => $item) {
                        if ($item->attributeId == $attribute->get('id')) {
                            // add to attribute group to result
                            $data['result']['list'][$key]->attributeGroupId = $attribute->get('attributeGroupId');
                            $data['result']['list'][$key]->attributeGroupName = $attribute->get('attributeGroupName');

                            // add sort order
                            $data['result']['list'][$key]->sortOrder = $attribute->get('sortOrder');
                        }
                    }
                }
            }

            // set data
            $event->setArgument('result', $data['result']);
        }
    }
}
