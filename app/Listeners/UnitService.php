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

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class UnitService extends AbstractEntityListener
{
    public function afterSetUnitAsDefault(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $conn = $this->getEntityManager()->getConnection();

        $conn->createQueryBuilder()
            ->update('attribute')
            ->set('default_unit', ':value')
            ->where('measure_id = :measureId')
            ->andWhere('deleted = :false')
            ->setParameter('value', $entity->get('id'))
            ->setParameter('measureId', $entity->get('measureId'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeStatement();
    }
}
