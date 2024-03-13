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
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Class AssociationEntity
 */
class AssociationEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if (empty($entity->get('isActive')) && $this->hasProduct($entity, true)) {
            throw new BadRequest($this->translate('youCanNotDeactivateAssociationWithActiveProducts', 'exceptions', 'Association'));
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if ($this->hasProduct($entity)) {
            throw new BadRequest($this->translate('associationIsLinkedWithProducts', 'exceptions', 'Association'));
        }
    }

    /**
     * Is association used in product(s)
     *
     * @param Entity $entity
     * @param bool   $isActive
     *
     * @return bool
     */
    protected function hasProduct(Entity $entity, bool $isActive = false): bool
    {
        $connection = $this->getEntityManager()->getConnection();

        $qb = $connection->createQueryBuilder()
            ->select('COUNT(ap.id) as total')
            ->from('associated_product', 'ap')
            ->innerJoin('ap', 'product', 'pm', 'pm.id = ap.main_product_id AND pm.deleted = :false')
            ->innerJoin('ap', 'product', 'pr', 'pr.id = ap.related_product_id AND pr.deleted = :false')
            ->where('ap.deleted = :false')
            ->andWhere('ap.association_id = :associationId')
            ->setParameter('associationId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('false', false, Mapper::getParameterType(false));

        if ($isActive) {
            $qb->andWhere('pm.is_active=:true OR pr.is_active=:true');
            $qb->setParameter('true', true, Mapper::getParameterType(true));
        }

        // get data
        $data = $qb->fetchAssociative();

        return !empty($data['total']);
    }
}
