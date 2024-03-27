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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Relation;
use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

class CategoryChannel extends Relation
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        $channelId = $entity->get('channelId');
        $categoryId = $entity->get('categoryId');

        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {

            foreach ($this->getEntityManager()->getRepository('Category')->getChildrenRecursivelyArray($categoryId) as $childId) {
                $options['pseudoTransactionManager']->pushCreateEntityJob('CategoryChannel', ['categoryId' => $childId, 'channelId' => $channelId]);
            }
        }
        parent::afterSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
            $childIds = $this->getEntityManager()->getRepository('Category')->getChildrenRecursivelyArray($entity->get('categoryId'));

            $ids = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('category_channel')
                ->where('channel_id = :channelId')
                ->where('category_id in (:childIds)')
                ->setParameter('channelId', $entity->get('channelId'))
                ->setParameter('childIds', $childIds, Mapper::getParameterType($childIds))
                ->fetchFirstColumn();

            foreach ($ids as $id) {
                $options['pseudoTransactionManager']->pushDeleteEntityJob('CategoryChannel', $id);
            }
        }

        parent::afterRemove($entity, $options);
    }
}