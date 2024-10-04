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

namespace Pim\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Relation;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ProductClassification extends Relation
{
    protected function afterSave(Entity $entity, array $data = [])
    {
        parent::afterSave($entity, $data);

        if ($this->getConfig()->get('allowSingleClassificationForProduct', false)) {
            $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->delete('product_classification')
                ->where('product_id=:productId AND classification_id <> :classificationId')
                ->setParameter('productId', $entity->get('productId'))
                ->setParameter('classificationId', $entity->get('classificationId'))
                ->executeQuery();
        }

        if ($entity->isNew()) {
            $this->getEntityManager()->getRepository('Product')->relateClassification($entity->get('productId'), $entity->get('classificationId'));
        }
    }

    protected function afterRemove(Entity $entity, $options = [])
    {
        $this->getEntityManager()->getRepository('Product')->unRelateClassification($entity->get('productId'), $entity->get('classificationId'));

        parent::afterRemove($entity);
    }
}
