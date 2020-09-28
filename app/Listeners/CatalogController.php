<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class CatalogController
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class CatalogController extends AbstractListener
{
    /**
     * @var string
     */
    protected $entityType = 'Catalog';

    /**
     * @param Event $event
     */
    public function afterActionRemoveLink(Event $event)
    {
        // get data
        $arguments = $event->getArguments();

        if ($arguments['params']['link'] === 'categories') {

            $categoryId = $arguments['data']->id;
            $catalogId = $arguments['params']['id'];

            $this->getService('ProductCategory')
                ->removeProductCategory($categoryId, $catalogId);
        }
    }
}
