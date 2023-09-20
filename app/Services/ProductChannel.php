<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Atro\Core\Templates\Services\Relationship;
use Espo\ORM\Entity;

class ProductChannel extends Relationship
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('isInherited', in_array($entity->get('channelId'), $this->getCategoriesChannelsIds($entity->get('productId'))));
    }

    protected function getCategoriesChannelsIds(string $productId): array
    {
        return $this->getEntityManager()->getRepository('Product')->getCategoriesChannelsIds($productId);
    }
}
