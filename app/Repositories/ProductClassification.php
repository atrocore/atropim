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
use Espo\ORM\Entity;

class ProductClassification extends Relation
{
    protected function init()
    {
        parent::init();
        $this->addDependency('language');
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if($this->getConfig()->get('allowSingleClassificationForProduct', false) &&  $entity->isNew()){
            $exists = $this->getEntityManager()->getConnection()
                ->createQueryBuilder()
                ->from('product_classification')
                ->select('id')
                ->where('product_id=:productId AND deleted=:false')
                ->setParameter('productId', $val = $entity->get('productId'), Mapper::getParameterType($val))
                ->setParameter('false',false, Mapper::getParameterType(false))
                ->fetchOne();

            if(!empty($exists)){
                throw new BadRequest(
                    $this->getInjection('language')->translate(
                        'onlySingleClassificationAllow',
                        'exceptions',
                        'Product')
                );
            }
        }
    }

    protected function afterSave(Entity $entity, array $data = [])
    {
        parent::afterSave($entity, $data);

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
