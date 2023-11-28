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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

class ProductClassification extends Relation
{
    protected function afterSave(Entity $entity, array $data = [])
    {
        parent::afterSave($entity, $data);
        $this->getEntityManager()->getRepository('Product')->relateClassification($entity->get('productId'), $entity->get('classificationId'));
    }

    protected function afterRemove(Entity $entity, $options = [])
    {
        $this->getEntityManager()->getRepository('Product')->unRelateClassification($entity->get('productId'), $entity->get('classificationId'));
        parent::afterRemove($entity);
    }
}
