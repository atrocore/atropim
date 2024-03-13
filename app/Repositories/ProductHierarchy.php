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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

class ProductHierarchy extends Relation
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('mainChild') && !empty($entity->get('mainChild'))) {
            $entities = $this->where([
                'parentId' => $entity->get('parentId'),
                'mainChild' => true,
                'id!=' => $entity->id
            ])->find();

            foreach ($entities as $item) {
                $item->set('mainChild', false);
                $this->save($item);
            }
        }

        parent::beforeSave($entity, $options);
    }
}
