<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Pim\Repositories\Category;
use Treo\Core\EventManager\Event;

/**
 * Class CatalogEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class CatalogEntity extends AbstractEntityListener
{
    /**
     * Before save
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        if (!$this->isCodeValid($event->getArgument('entity'))) {
            throw new BadRequest(
                $this->translate('Code is invalid', 'exceptions', 'Global')
            );
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'categories'
            && !empty($foreign = $event->getArgument('foreign'))
            && !is_string($foreign)
            && !empty($foreign->get('categoryParent'))) {
            throw new BadRequest($this->exception('Only root category can be linked with catalog'));
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'categories') {
            $this->getCategoryRepository()->canUnRelateCatalog($event->getArgument('foreign'), $event->getArgument('entity'));
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Catalog');
    }

    /**
     * @return Category
     */
    protected function getCategoryRepository(): Category
    {
        return $this->getEntityManager()->getRepository('Category');
    }
}
