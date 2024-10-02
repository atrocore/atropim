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
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $product = $entity->get('product');
            $classification = $entity->get('classification');
            if (empty($product) || empty($classification)) {
                throw new BadRequest();
            }

            $channels = $product->get('channels');
            if (!empty($channels)) {
                $channelIds = [];
                foreach ($channels as $channel) {
                    $channelIds[] = $channel->get('id');
                }

                $res = $this->getEntityManager()->getConnection()->createQueryBuilder()
                    ->select('c.id')
                    ->from('classification', 'c')
                    ->leftJoin('c', 'channel_classification', 'cc', "c.id = cc.classification_id and cc.deleted = :false")
                    ->where('c.id = :id')
                    ->andwhere("cc.channel_id IS NULL OR cc.channel_id IN (:channelIds)")
                    ->setParameter('channelIds', $channelIds, Mapper::getParameterType($channelIds))
                    ->setParameter('id', $entity->get('classificationId'))
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchFirstColumn();

                if (empty($res)) {
                    throw new BadRequest(str_replace(':name', $classification->get('name'), $this->getLanguage()->translate('classificationCannotBeLinked', 'exceptions', 'Product')));
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

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
