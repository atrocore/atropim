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
use Espo\ORM\Entity;

class ListingClassification extends Relation
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (empty($this->getMemoryStorage()->get('listingClassificationUpdated'))) {
            $this->getConnection()->createQueryBuilder()
                ->update('listing')
                ->set('classification_id', ':classificationId')
                ->where('id=:listingId')
                ->setParameter('classificationId', $entity->get('classificationId'))
                ->setParameter('listingId', $entity->get('listingId'))
                ->executeQuery();
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if (empty($this->getMemoryStorage()->get('listingClassificationUpdated'))) {
            $classification = $this->getEntityManager()->getRepository('Classification')->get($entity->get('classificationId'));
            $listing = $this->getEntityManager()->getRepository('Listing')->get($entity->get('listingId'));

            if (!empty($classification) && !empty($listing)) {
                throw new BadRequest($this->getLanguage()->translate('singleClassificationAllowed', 'exceptions'));
            }
        }
    }
}
