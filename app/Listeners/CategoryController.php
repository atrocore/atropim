<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class CategoryController
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class CategoryController extends AbstractListener
{
    /**
     * @var string
     */
    protected $entityType = 'Category';

    /**
     * @param Event $event
     */
    public function beforeActionUpdate(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if (isset($data['data']->categoryParentId) && !empty($categoryParentId = $data['data']->categoryParentId)) {
            if ($this->getService($this->entityType)->isChildCategory($data['params']['id'], $categoryParentId)) {
                $message = $this
                    ->getLanguage()
                    ->translate("You can not choose a child category", 'exceptions', 'Category');

                throw new BadRequest($message);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function beforeActionPatch(Event $event)
    {
        $this->beforeActionUpdate($event);
    }
}
