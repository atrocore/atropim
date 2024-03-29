<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class AssetEntity extends AbstractEntityListener
{
    public function afterRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        // remove related pavs
        $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');
        $pavs = $repository->select(['id'])->where(['attributeType' => 'asset', 'referenceValue' => $entity->get('fileId')])->find();
        foreach ($pavs as $pav) {
            $repository->remove($pav);
        }

        // remove related product_assets
        $repository = $this->getEntityManager()->getRepository('ProductAsset');
        $pas = $repository->select(['id'])->where(['assetId' => $entity->get('id')])->find();
        foreach ($pas as $pa) {
            $repository->remove($pa);
        }
    }
}
