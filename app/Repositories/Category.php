<?php

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Category
 *
 * @author Roman Ratsun <r.ratsun@gmail.com>
 */
class Category extends Base
{
    /**
     * @param Entity $entity
     * @param array  $options
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entity->set('sortOrder', time());
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('_position'))) {
            $this->updateSortOrderInTree($entity);
        }

        parent::afterSave($entity, $options);
    }

    /**
     * @param Entity $entity
     */
    protected function updateSortOrderInTree(Entity $entity): void
    {
        // prepare sort order
        $sortOrder = 0;

        // prepare data
        $data = [];

        if ($entity->get('_position') == 'after') {
            // prepare sort order
            $sortOrder = $this->select(['sortOrder'])->where(['id' => $entity->get('_target')])->findOne()->get('sortOrder');

            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => [$entity->get('_target'), $entity->get('id')],
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();

            // increase sort order
            $sortOrder = $sortOrder + 10;

        } elseif ($entity->get('_position') == 'inside') {
            // get collection
            $data = $this
                ->select(['id'])
                ->where(
                    [
                        'id!='             => $entity->get('id'),
                        'sortOrder>='      => $sortOrder,
                        'categoryParentId' => $entity->get('categoryParentId')
                    ]
                )
                ->order('sortOrder')
                ->find()
                ->toArray();
        }

        // prepare data
        $data = array_merge([$entity->get('id')], array_column($data, 'id'));

        // prepare sql
        $sql = '';
        foreach ($data as $id) {
            // prepare sql
            $sql .= "UPDATE category SET sort_order=$sortOrder WHERE id='$id';";

            // increase sort order
            $sortOrder = $sortOrder + 10;
        }

        // execute sql
        $this->getEntityManager()->nativeQuery($sql);
    }
}
