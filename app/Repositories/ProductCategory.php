<?php

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class ProductCategory
 *
 * @author r.ratsun@gmail.com
 */
class ProductCategory extends Base
{
    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        // call parent action
        parent::beforeSave($entity, $options);

        if (!$entity->isNew() && $entity->isAttributeChanged('sorting')) {
            $this->updateSortOrder($entity);
        }
    }

    /**
     * @inheritDoc
     */
    public function max($field)
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery("SELECT MAX(sorting) AS max FROM product_category WHERE deleted=0")
            ->fetch(\PDO::FETCH_ASSOC);

        return $data['max'];
    }

    /**
     * @param Entity $entity
     */
    protected function updateSortOrder(Entity $entity): void
    {
        $data = $this
            ->select(['id'])
            ->where(
                [
                    'id!='       => $entity->get('id'),
                    'sorting>='  => $entity->get('sorting'),
                    'categoryId' => $entity->get('categoryId')
                ]
            )
            ->order('sorting')
            ->find()
            ->toArray();

        if (!empty($data)) {
            // create max
            $max = $entity->get('sorting');

            // prepare sql
            $sql = '';
            foreach ($data as $row) {
                // increase max
                $max  = $max + 10;

                // prepare id
                $id = $row['id'];

                // prepare sql
                $sql .= "UPDATE product_category SET sorting='$max' WHERE id='$id';";
            }

            // execute sql
            $this->getEntityManager()->nativeQuery($sql);
        }
    }
}
