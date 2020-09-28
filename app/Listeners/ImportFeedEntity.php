<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;
use Espo\Core\Utils\Json;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

/**
 * Class ImportFeedEntity
 *
 * @author r.ratsun@gmail.com
 */
class ImportFeedEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws Error
     */
    public function beforeSave(Event $event)
    {
        $entity = $event->getArgument('entity');

        if (!$this->isConfiguratorValid($entity)) {
            throw new Error('Configurator settings incorrect');
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isConfiguratorValid(Entity $entity): bool
    {
        $configurator = Json::decode(Json::encode($entity->get('data')->configuration), true);

        foreach ($configurator as $key => $item) {
            // check for same attributes
            if (isset($item['attributeId'])) {
                foreach ($configurator as $k => $i) {
                    if (isset($i['attributeId']) && $i['attributeId'] == $item['attributeId']
                        && $i['scope'] == $item['scope'] && $key != $k && $i['locale'] == $item['locale']) {
                        if ($item['scope'] == 'Channel'
                            && empty(array_intersect($item['channelsIds'], $i['channelsIds']))) {
                            continue;
                        }

                        return false;
                    }
                }
            }

            // check for the same product categories
            if ($item['name'] == 'productCategories') {
                foreach ($configurator as $k => $i) {
                    if ($i['name'] == $item['name'] && $i['scope'] == $item['scope'] && $key != $k) {
                        if ($item['scope'] == 'Channel'
                            && empty(array_intersect($item['channelsIds'], $i['channelsIds']))) {
                            continue;
                        }

                        return false;
                    }
                }
            }
        }

        return true;
    }
}
