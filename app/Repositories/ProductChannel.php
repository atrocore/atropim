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
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ProductChannel extends Relation
{
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('pc.id')
            ->from('product_classification', 'pc')
            ->innerJoin('pc', 'classification', 'c', 'c.id = pc.classification_id AND c.deleted = :false')
            ->innerJoin('c', 'channel_classification', 'cc', 'c.id = cc.classification_id AND cc.channel_id = :channelId AND cc.deleted = :false')
            ->where('pc.deleted = :false')
            ->andWhere('pc.product_id = :productId')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('productId', $entity->get('productId'))
            ->setParameter('channelId', $entity->get('channelId'))
            ->fetchAllAssociative();


        if (!empty($res[0])) {
            throw new \Atro\Core\Exceptions\BadRequest($this->getLanguage()->translate("cannotUnlinkClassificationChannel", 'exceptions', $entity->getEntityType()));
        }

        parent::beforeRemove($entity, $options);
    }
}
