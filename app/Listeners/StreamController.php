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

namespace Pim\Listeners;

use Caxy\HtmlDiff\HtmlDiff;
use Espo\Core\EventManager\Event;
use Espo\Listeners\AbstractListener;

/**
 * Class StreamController
 */
class StreamController extends AbstractListener
{
    /**
     * After action list
     *
     * @param Event $event
     */
    public function afterActionList(Event $event)
    {
        $result = $event->getArgument('result');
        $params = $event->getArgument('params');

//        $result = $this->prepareDataForUserStream($result, $params);
//        $result = $this->injectAttributeType($result);
//        $result = $this->injectDiffForTextAttributes($result);

        $event->setArgument('result', $result);
    }

    /**
     * @param array $result
     *
     * @return array
     */
    protected function injectDiffForTextAttributes(array $result): array
    {
        if (isset($result['list']) && is_array($result['list'])) {
            foreach ($result['list'] as $key => $item) {
                if (isset($item['attributeType']) && $item['attributeType'] == 'text') {
                    $data = $item['data'];

                    $was = $data->attributes->was->{$data->fields[0]};
                    $became = nl2br($data->attributes->became->{$data->fields[0]});

                    $diff = (new HtmlDiff(html_entity_decode($was), html_entity_decode($became)))->build();

                    $result['list'][$key]['diff'] = $diff;
                }
            }
        }

        return $result;
    }

    /**
     * Inject attribute type in data
     *
     * @param array $result
     *
     * @return array
     */
    protected function injectAttributeType(array $result): array
    {
        if (isset($result['list']) && is_array($result['list'])) {
            $pavsIds = array_unique(array_column($result['list'], 'attributeId'));

            if (!empty($attributes = $this->getAttributesData($pavsIds))) {
                foreach ($result['list'] as $key => $item) {
                    if (isset($item['attributeId']) && isset($attributes[$item['attributeId']])) {
                        $type = $attributes[$item['attributeId']]['type'];
                        $result['list'][$key]['attributeType'] = $type;

                        if ($type === 'image') {
                            foreach ($result['list'][$key]['data']->fields as $field) {
                                $becameValue = $result["list"][$key]["data"]->attributes->became->{$field};
                                $result["list"][$key]["data"]->attributes->became->{$field . 'Id'} = $becameValue;
                                unset ($result["list"][$key]["data"]->attributes->became->{$field});

                                $wasValue = $result["list"][$key]["data"]->attributes->was->{$field};
                                $result["list"][$key]["data"]->attributes->was->{$field . 'Id'} = $wasValue;
                                unset ($result["list"][$key]["data"]->attributes->was->{$field});
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Prepare data for user stream panel in dashlet
     *
     * @param array $result
     * @param array $params
     *
     * @return array
     */
    protected function prepareDataForUserStream(array $result, array $params): array
    {
        if (!empty($result['list']) && isset($params['scope']) && $params['scope'] == 'User') {
            // prepare notes ids
            $noteIds = array_column($result['list'], 'id');

            if (!empty($noteIds)) {
                // get notes attributeId field
                $items = $this
                    ->getEntityManager()
                    ->getRepository('Note')
                    ->select(['id', 'attributeId'])
                    ->where(['id' => $noteIds])
                    ->find()
                    ->toArray();

                if (!empty($items)) {
                    $items = array_column($items, 'attributeId', 'id');

                    // set attributeId field where needed in result
                    foreach ($result['list'] as $key => $value) {
                        if (isset($items[$value['id']])) {
                            $result['list'][$key]['attributeId'] = $items[$value['id']];
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array $ids
     *
     * @return array
     */
    protected function getAttributesData(array $ids): array
    {
        $result = [];

        $attributes = $this->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['id' => $ids])
            ->find();

        if (count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                if (!empty($attribute->get('attribute'))) {
                    $result[$attribute->get('id')]['type'] = $attribute->get('attribute')->get('type');
                }
            }
        }

        return $result;
    }
}
