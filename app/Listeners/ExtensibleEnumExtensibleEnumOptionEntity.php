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
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ExtensibleEnumExtensibleEnumOptionEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $conn = $this->getEntityManager()->getConnection();

        $ca = $conn->createQueryBuilder()
            ->select('t.*')
            ->from($conn->quoteIdentifier('classification_attribute'), 't')
            ->join('t','attribute','a','t.attribute_id=a.id')
            ->where("t.reference_value = :referenceValue OR t.text_value LIKE :textValue")
            ->andWhere('a.extensible_enum_id=:extensibleEnumId')
            ->andWhere('t.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('referenceValue', $entity->get('extensibleEnumOptionId'), ParameterType::STRING)
            ->setParameter('textValue', "%\"{$entity->get('extensibleEnumOptionId')}\"%", ParameterType::STRING)
            ->setParameter('extensibleEnumId', $param = $entity->get('extensibleEnumId'), Mapper::getParameterType($param))
            ->fetchAssociative();

        if (!empty($ca)) {
            $caEntity = $this->getEntityManager()->getRepository('ClassificationAttribute')->get($ca['id']);
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('extensibleEnumOptionIsUsedOnClassificationAttribute', 'exceptions', 'ExtensibleEnumOption'),
                    $entity->get('name'),
                    $caEntity->get('attributeName') ?? $caEntity->get('attributeId'),
                    $caEntity->get('classificationName') ?? $caEntity->get('classificationId')
                )
            );
        }
    }
}
