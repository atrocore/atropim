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

namespace Pim\AttributeConverters;

use Doctrine\DBAL\Connection;
use Espo\ORM\Entity;

class ToExtensibleMultiEnum implements AttributeConverterInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function convert(Entity $attribute): void
    {
        $attribute->set('prohibitedEmptyValue', false);

        while (true) {
            $res = $this->connection->createQueryBuilder()
                ->select('pav.id, pav.varchar_value')
                ->from($this->connection->quoteIdentifier('product_attribute_value'), 'pav')
                ->where('pav.attribute_id = :attributeId')
                ->setParameter('attributeId', $attribute->get('id'))
                ->andWhere('pav.attribute_type = :attributeType')
                ->setParameter('attributeType', 'extensibleEnum')
                ->setFirstResult(0)
                ->setMaxResults(5000)
                ->fetchAllAssociative();

            if (empty($res)) {
                break;
            }

            foreach ($res as $v) {
                $textValue = empty($v['varchar_value']) ? null : "[\"{$v['varchar_value']}\"]";
                $this->connection->createQueryBuilder()
                    ->update($this->connection->quoteIdentifier('product_attribute_value'))
                    ->set('text_value', ':textValue')
                    ->setParameter('textValue', $textValue)
                    ->set('varchar_value', ':nullValue')
                    ->setParameter('nullValue', null)
                    ->set('attribute_type', ':attributeType')
                    ->setParameter('attributeType', 'extensibleMultiEnum')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            }
        }
    }
}