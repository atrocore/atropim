<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class AssetEntity
 * @package Pim\Listeners
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class AssetEntity extends AbstractListener
{
    /** @var array */
    protected $hasMainImage = ['Product', 'Category'];

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event): void
    {
        $fileId = $event->getArgument('entity')->get('fileId');
        foreach ($this->hasMainImage as $entity) {
            $table = Util::toCamelCase($entity);
            $this
                ->getEntityManager()
                ->nativeQuery('UPDATE '. $table .' SET image_id = null WHERE image_id = :id', ['id' => $fileId]);
        }
    }
}
