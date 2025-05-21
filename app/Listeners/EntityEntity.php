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
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class EntityEntity extends AbstractEntityListener
{
    public function beforeSave(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->isAttributeChanged('hasAttribute') && empty($entity->get('hasAttribute'))) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')
                ->where(['entityId' => $entity->id])
                ->findOne();

            if (!empty($attribute)) {
                throw new BadRequest($this->getLanguage()->translate('entityAlreadyHasAttributesInUse', 'exceptions', 'Entity'));
            }
        }

        if ($entity->get('hasSingleClassificationOnly') && $entity->get('hasClassification')) {
            $mapper = $this->getEntityManager()->getMapper();
            $entityColumn = $mapper->toDb(lcfirst($entity->get('code')) . 'Id');
            $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
            $classifications = $qb->select($entityColumn)
                ->from($mapper->toDb($entity->get('code') . 'Classification'), 'ec')
                ->where('ec.deleted = :false')
                ->groupBy($entityColumn)
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->having('COUNT(*) > 1')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAllAssociative();

            if (!empty($classifications)) {
                throw new BadRequest($this->getLanguage()->translate('moreThanOneClassification', 'exceptions'));
            }
        }
    }
}
