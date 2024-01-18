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

class ExtensibleEnumOptionEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $conn = $this->getEntityManager()->getConnection();

        $pav = $conn->createQueryBuilder()
            ->select('t.*')
            ->from($conn->quoteIdentifier('product_attribute_value'), 't')
            ->where("(t.reference_value = :referenceValue and t.attribute_type = :extensibleEnum) OR (t.text_value LIKE :textValue and t.attribute_type = :extensibleMultiEnum)")
            ->andWhere('t.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('referenceValue', $entity->get('id'))
            ->setParameter('textValue', "%\"{$entity->get('id')}\"%")
            ->setParameter('extensibleEnum', 'extensibleEnum')
            ->setParameter('extensibleMultiEnum', 'extensibleMultiEnum')
            ->fetchAssociative();

        if (!empty($pav)) {
            $pavEntity = $this->getEntityManager()->getRepository('ProductAttributeValue')->get($pav['id']);
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('extensibleEnumOptionIsUsedOnAttribute', 'exceptions', 'ExtensibleEnumOption'),
                    $entity->get('name'),
                    $pavEntity->get('attributeName') ?? $pavEntity->get('attributeId'),
                    $pavEntity->get('productName') ?? $pavEntity->get('productId')
                )
            );
        }
    }
}
