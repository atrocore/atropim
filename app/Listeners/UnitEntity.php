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

class UnitEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $conn = $this->getEntityManager()->getConnection();

        $pav = $conn->createQueryBuilder()
            ->select('t.*')
            ->from($conn->quoteIdentifier('product_attribute_value'), 't')
            ->where('t.reference_value = :unitId')
            ->andWhere('t.deleted = :false')
            ->setParameter('unitId', $entity->get('id'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($pav)) {
            $pavEntity = $this->getEntityManager()->getRepository('ProductAttributeValue')->get($pav['id']);
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('unitIsUsedOnProductAttribute', 'exceptions', 'Unit'),
                    $entity->get('name'),
                    $pavEntity->get('attributeName') ?? $pavEntity->get('attributeId'),
                    $pavEntity->get('productName') ?? $pavEntity->get('productId')
                )
            );
        }

        $ca = $conn->createQueryBuilder()
            ->select('t.*')
            ->from($conn->quoteIdentifier('classification_attribute'), 't')
            ->where('t.reference_value = :unitId')
            ->andWhere('t.deleted = :false')
            ->setParameter('unitId', $entity->get('id'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($ca)) {
            $caEntity = $this->getEntityManager()->getRepository('ClassificationAttribute')->get($ca['id']);
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('unitIsUsedOnClassificationAttribute', 'exceptions', 'Unit'),
                    $entity->get('name'),
                    $caEntity->get('attributeName') ?? $caEntity->get('attributeId'),
                    $caEntity->get('classificationName') ?? $caEntity->get('classificationId')
                )
            );
        }
    }
}
