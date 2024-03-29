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

class CategoryFile extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && $entity->get('sorting') === null) {
            $entity->set('sorting', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isMainImage') && !empty($entity->get('isMainImage'))) {
            $categoryAssets = $this
                ->select(['id', 'isMainImage'])
                ->where(['isMainImage' => true, 'categoryId' => $entity->get('categoryId'), 'id!=' => $entity->get('id')])
                ->find();

            foreach ($categoryAssets as $categoryAsset) {
                $categoryAsset->set('isMainImage', false);
                $this->getEntityManager()->saveEntity($categoryAsset);
            }
        }
    }

    public function updateSortOrder(string $categoryId, array $filesIds): void
    {
        $collection = $this->where(['categoryId' => $categoryId, 'fileId' => $filesIds])->find();
        if (empty($collection[0])) {
            return;
        }

        foreach ($filesIds as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('fileId') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }
}
