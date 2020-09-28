<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class ProductFamilyController
 *
 * @author r.ratsun@gmail.com
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
