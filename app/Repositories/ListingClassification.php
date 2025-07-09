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

class ListingClassification extends Relation
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->getConnection()->createQueryBuilder()
            ->update('listing')
            ->set('classification_id', ':classificationId')
            ->where('id=:listingId')
            ->setParameter('classificationId', $entity->get('classificationId'))
            ->setParameter('listingId', $entity->get('listingId'))
            ->executeQuery();
    }
}
