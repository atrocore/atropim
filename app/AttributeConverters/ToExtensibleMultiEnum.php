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

namespace Pim\AttributeConverters;

use Doctrine\DBAL\Connection;
use Espo\ORM\Entity;

class ToExtensibleMultiEnum implements AttributeConverterInterface
{
    public const LIMIT = 5000;

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
                ->setMaxResults(self::LIMIT)
                ->fetchAllAssociative();

            if (empty($res)) {
                break;
            }

            foreach ($res as $v) {
                $this->connection->createQueryBuilder()
                    ->update($this->connection->quoteIdentifier('product_attribute_value'))
                    ->set('text_value', ':textValue')
                    ->setParameter('textValue', $this->prepareTextValue($v['varchar_value']))
                    ->set('varchar_value', ':nullValue')
                    ->setParameter('nullValue', null)
                    ->set('attribute_type', ':attributeType')
                    ->setParameter('attributeType', 'extensibleMultiEnum')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            }
        }

        while (true) {
            $res = $this->connection->createQueryBuilder()
                ->select('ca.id, ca.varchar_value')
                ->from($this->connection->quoteIdentifier('classification_attribute'), 'ca')
                ->where('ca.attribute_id = :attributeId')
                ->setParameter('attributeId', $attribute->get('id'))
                ->andWhere('ca.varchar_value IS NOT NULL')
                ->setFirstResult(0)
                ->setMaxResults(self::LIMIT)
                ->fetchAllAssociative();

            if (empty($res)) {
                break;
            }

            foreach ($res as $v) {
                $this->connection->createQueryBuilder()
                    ->update($this->connection->quoteIdentifier('classification_attribute'))
                    ->set('text_value', ':textValue')
                    ->setParameter('textValue', $this->prepareTextValue($v['varchar_value']))
                    ->set('varchar_value', ':nullValue')
                    ->setParameter('nullValue', null)
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            }
        }
    }

    protected function prepareTextValue($varcharValue): ?string
    {
        if ($varcharValue === null) {
            return null;
        }

        return "[\"{$varcharValue}\"]";
    }
}