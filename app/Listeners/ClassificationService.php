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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ClassificationService extends AbstractEntityListener
{
    public function prepareEntityForOutput(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');
        $result = false;
        if(empty( $entity->_loadedFromCollection)) {
            $result = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('1')
                ->from('listing')
                ->where('classification_id=:classificationId')
                ->andWhere('deleted = :false')
                ->setParameter('classificationId', $entity->get('id'))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();
        }

        $entity->set('hasListing', !empty($result));
    }

    public function prepareCollectionForOutput(Event $event): void
    {
        $collection = $event->getArgument('collection');
        foreach ($collection as $entity) {
            $entity->_loadedFromCollection = true;
        }
    }
}
