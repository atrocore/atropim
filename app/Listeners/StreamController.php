<?php

namespace Pim\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class StreamController
 *
 * @author r.ratsun@gmail.com
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

        $result = $this->prepareDataForUserStream($result);
        $result = $this->injectAttributeType($result);

        $event->setArgument('result', $result);
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
            if (!empty($attributes = $this->getAttributesType(array_column($result['list'], 'attributeId')))) {
                foreach ($result['list'] as $key => $item) {
                    if (isset($attributes[$item['attributeId']])) {
                        $result['list'][$key]['attributeType'] = $attributes[$item['attributeId']];
                        if ($result["list"][$key]["attributeType"] === 'image') {
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
     *
     * @return array
     */
    protected function prepareDataForUserStream(array $result): array
    {
        if (!empty($result['list']) && isset($result['scope']) && $result['scope'] == 'User') {
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
    protected function getAttributesType(array $ids): array
    {
        $result = [];

        $attributes = $this->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['id' => $ids])
            ->find();

        if (count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                if (!empty($attribute->get('attribute'))) {
                    $result[$attribute->get('id')] = $attribute->get('attribute')->get('type');
                }
            }
        }

        return $result;
    }
}
