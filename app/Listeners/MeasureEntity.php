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

class MeasureEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $conn = $this->getEntityManager()->getConnection();

        $attribute = $conn->createQueryBuilder()
            ->select('t.*')
            ->from($conn->quoteIdentifier('attribute'), 't')
            ->where('t.measure_id = :measureId')
            ->andWhere('t.deleted = :false')
            ->setParameter('measureId', $entity->get('id'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($attribute)) {
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('measureIsUsedOnAttribute', 'exceptions', 'Measure'),
                    $entity->get('name') ?? $entity->get('id'),
                    $attribute['name'] ?? $attribute['id']
                )
            );
        }

    }
}
