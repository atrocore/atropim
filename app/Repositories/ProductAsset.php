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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;

class ProductAsset extends Relationship
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && $entity->get('sorting') === null) {
            $last = $this->where(['productId' => $entity->get('productId')])->order('sorting', 'DESC')->findOne();
            $entity->set('sorting', empty($last) ? 0 : (int)$last->get('sorting') + 10);
        }

        // for unique index
        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isMainImage') && !empty($entity->get('isMainImage'))) {
            foreach ($this->where(['isMainImage' => true, 'productId' => $entity->get('productId'), 'id!=' => $entity->get('id')])->find() as $productAsset) {
                $productAsset->set('isMainImage', false);
                $this->getEntityManager()->saveEntity($productAsset);
            }
        }
    }

    public function updateSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (count($collection) === 0) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }
}
