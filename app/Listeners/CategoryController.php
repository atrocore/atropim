<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Pim\Services\Category;
use Pim\Services\ProductCategory;
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

    /**
     * @param Event $event
     */
    public function afterActionDelete(Event $event)
    {
        $categoryId = $event->getArgument('params')['id'];

        $this->getService('Category')->removeProductCategoryByCategory($categoryId);
    }

    public function afterActionMassDelete(Event $event)
    {
        $categoryIds = $event->getArgument('data')->ids;
        foreach ($categoryIds as $categoryId) {
            $this->getService('Category')->removeProductCategoryByCategory($categoryId);
        }
    }

    /**
     * @param Event $event
     */
    public function afterActionRemoveLink(Event $event)
    {
        // get data
        $arguments = $event->getArguments();

        if ($arguments['params']['link'] === 'catalogs') {

            $categoryId = $arguments['params']['id'];
            $catalogId = $arguments['data']->id;

            $this->getService('ProductCategory')->removeProductCategory($categoryId, $catalogId);
        }
    }
}
