<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;

class AssetService extends AbstractEntityListener
{
    public function afterCreateEntity(Event $event): void
    {
        $attachment = $event->getArgument('attachment');
        $entity = $event->getArgument('entity');

        if (property_exists($attachment, '_createAssetRelation') && !empty($attachment->_createAssetRelation)) {
            $entityId = $attachment->_createAssetRelation->entityId;
            $entityType = $attachment->_createAssetRelation->entityType;
            $link = lcfirst($entityType) . 'Id';

            $input = new \stdClass();
            $input->assetId = $entity->get('id');
            $input->$link = $entityId;
            try {
                $this->getServiceFactory()->create($entityType . 'Asset')->createEntity($input);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('ProductAsset creating failed: ' . $e->getMessage());
            }
        }
    }
}
