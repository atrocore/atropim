<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-09 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_product_attribute_value_channel_id");
            $this->exec("DROP INDEX idx_product_attribute_value_modified_at");
            $this->exec("DROP INDEX idx_product_attribute_value_created_by_id");
            $this->exec("DROP INDEX idx_product_attribute_value_modified_by_id");
            $this->exec("DROP INDEX idx_product_attribute_value_created_at");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP");

            $this->exec("ALTER TABLE product_attribute_value ADD json_value TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN product_attribute_value.json_value IS '(DC2Type:jsonObject)'");

            $this->exec("CREATE TABLE variant_specific_product_attribute (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, product_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_UNIQUE_RELATION ON variant_specific_product_attribute (deleted, product_id, attribute_id)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_BY_ID ON variant_specific_product_attribute (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_BY_ID ON variant_specific_product_attribute (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_PRODUCT_ID ON variant_specific_product_attribute (product_id, deleted)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_ATTRIBUTE_ID ON variant_specific_product_attribute (attribute_id, deleted)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_AT ON variant_specific_product_attribute (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_AT ON variant_specific_product_attribute (modified_at, deleted)");
        } else {
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_MODIFIED_BY_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_MODIFIED_AT ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CHANNEL_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CREATED_AT ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CREATED_BY_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value");

            $this->exec("ALTER TABLE product_attribute_value ADD json_value LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");

            $this->exec("CREATE TABLE variant_specific_product_attribute (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, product_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_UNIQUE_RELATION (deleted, product_id, attribute_id), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_PRODUCT_ID (product_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_ATTRIBUTE_ID (attribute_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_AT (created_at, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
            $this->exec("ALTER TABLE product_attribute_value ADD varchar_value_" . strtolower($language) . " VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE product_attribute_value ADD text_value_" . strtolower($language) . " TEXT DEFAULT NULL");
        }

        $this->exec("ALTER TABLE attribute ADD channel_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ATTRIBUTE_CHANNEL_ID ON attribute (channel_id, deleted)");

        $this->exec("ALTER TABLE product_attribute_value DROP attribute_type");
        $this->exec("ALTER TABLE product_attribute_value DROP created_at");
        $this->exec("ALTER TABLE product_attribute_value DROP modified_at");
        $this->exec("ALTER TABLE product_attribute_value DROP created_by_id");
        $this->exec("ALTER TABLE product_attribute_value DROP modified_by_id");
        $this->exec("ALTER TABLE product_attribute_value DROP count_bytes_instead_of_characters");

        $this->migrateArrayType();
        $this->migrateMultilang();
        $this->migrateChannelSpecific();
        $this->migrateVariantSpecific();

        $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value (deleted, product_id, attribute_id)");

        $this->exec("ALTER TABLE classification ADD entity_id VARCHAR(36) DEFAULT NULL");
        $this->getConnection()->createQueryBuilder()
            ->update('classification')
            ->set('entity_id', ':entityId')
            ->where('deleted=:false')
            ->setParameter('entityId', 'Product')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    protected function migrateVariantSpecific(): void
    {
        while (true) {
            try {
                $res = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from('product_attribute_value')
                    ->where('deleted=:false')
                    ->andWhere('is_variant_specific_attribute=:true')
                    ->setFirstResult(0)
                    ->setMaxResults(5000)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('true', true, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                return;
            }

            if (empty($res)) {
                break;
            }

            foreach ($res as $item) {
                try {
                    $this->getConnection()->createQueryBuilder()
                        ->insert('variant_specific_product_attribute')
                        ->setValue('id', ':id')
                        ->setValue('created_at', ':created_at')
                        ->setValue('modified_at', ':modified_at')
                        ->setValue('created_by_id', ':created_by_id')
                        ->setValue('modified_by_id', ':modified_by_id')
                        ->setValue('product_id', ':product_id')
                        ->setValue('attribute_id', ':attribute_id')
                        ->setParameter('id', Util::generateId())
                        ->setParameter('created_at', date('Y-m-d H:i:s'))
                        ->setParameter('modified_at', date('Y-m-d H:i:s'))
                        ->setParameter('created_by_id', 'system')
                        ->setParameter('modified_by_id', 'system')
                        ->setParameter('product_id', $item['product_id'])
                        ->setParameter('attribute_id', $item['attribute_id'])
                        ->executeQuery();
                } catch (\Throwable $e) {
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('is_variant_specific_attribute', ':false')
                    ->where('id=:id')
                    ->setParameter('id', $item['id'])
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE product_attribute_value DROP is_variant_specific_attribute;");
    }

    protected function migrateChannelSpecific(): void
    {
        while (true) {
            try {
                $res = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from('product_attribute_value')
                    ->where('deleted=:false')
                    ->andWhere('channel_id IS NOT NULL AND channel_id !=:empty')
                    ->setFirstResult(0)
                    ->setMaxResults(5000)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('empty', '')
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                return;
            }

            if (empty($res)) {
                break;
            }

            foreach ($res as $item) {
                $attribute = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from($this->getConnection()->quoteIdentifier('attribute'))
                    ->where('id=:id')
                    ->setParameter('id', $item['attribute_id'])
                    ->fetchAssociative();

                if (empty($attribute)) {
                    continue;
                }

                $attributeId = md5($attribute['id'] . '_' . $item['channel_id']);

                $qb = $this->getConnection()->createQueryBuilder()
                    ->insert($this->getConnection()->quoteIdentifier('attribute'));

                foreach ($attribute as $column => $val) {
                    if ($column === 'id') {
                        $qb->setValue('id', ':id')
                            ->setParameter('id', $attributeId);
                    } elseif ($column === 'channel_id') {
                        $qb->setValue('channel_id', ':channelId')
                            ->setParameter('channelId', $item['channel_id']);
                    } else {
                        $qb->setValue($this->getConnection()->quoteIdentifier($column), ":$column")
                            ->setParameter($column, $val, Mapper::getParameterType($val));
                    }
                }

                try {
                    $qb->executeQuery();
                } catch (\Throwable $e) {
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('channel_id', ':empty')
                    ->set('attribute_id', ':attributeId')
                    ->where('id=:id')
                    ->setParameter('empty', '')
                    ->setParameter('attributeId', $attributeId)
                    ->setParameter('id', $item['id'])
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE product_attribute_value DROP channel_id");
    }

    protected function migrateArrayType(): void
    {
        while (true) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('av.*')
                ->from('product_attribute_value', 'av')
                ->leftJoin('av', $this->getConnection()->quoteIdentifier('attribute'), 'a',
                    'a.id=av.attribute_id AND a.deleted=:false')
                ->where('a.type IN (:types)')
                ->andWhere('av.deleted=:false')
                ->andWhere('av.text_value IS NOT NULL')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('types', ['extensibleMultiEnum', 'array'], $this->getConnection()::PARAM_STR_ARRAY)
                ->setFirstResult(0)
                ->setMaxResults(5000)
                ->fetchAllAssociative();

            if (empty($res)) {
                break;
            }

            foreach ($res as $item) {
                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('json_value', ':jsonValue')
                    ->set('text_value', ':null')
                    ->where('id=:id')
                    ->setParameter('jsonValue', $item['text_value'])
                    ->setParameter('null', null, ParameterType::NULL)
                    ->setParameter('id', $item['id'])
                    ->executeQuery();
            }
        }
    }

    protected function migrateMultilang(): void
    {
        while (true) {
            try {
                $res = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from('product_attribute_value')
                    ->where('language!=:main')
                    ->andWhere('deleted=:false')
                    ->setFirstResult(0)
                    ->setMaxResults(5000)
                    ->setParameter('main', 'main')
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                return;
            }

            if (empty($res)) {
                break;
            }

            foreach ($res as $record) {
                // move value to lingual column
                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('varchar_value_' . strtolower($record['language']), ':varcharValue')
                    ->set('text_value_' . strtolower($record['language']), ':textValue')
                    ->where('language=:main')
                    ->andWhere('product_id=:productId')
                    ->andWhere('attribute_id=:attributeId')
                    ->andWhere('channel_id=:channelId')
                    ->andWhere('deleted=:false')
                    ->setParameter('varcharValue', $record['varchar_value'])
                    ->setParameter('textValue', $record['text_value'])
                    ->setParameter('main', 'main')
                    ->setParameter('productId', $record['product_id'])
                    ->setParameter('channelId', $record['channel_id'])
                    ->setParameter('attributeId', $record['attribute_id'])
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeQuery();

                // delete lingual record
                $this->getConnection()->createQueryBuilder()
                    ->delete('product_attribute_value')
                    ->where('id=:id')
                    ->setParameter('id', $record['id'])
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE product_attribute_value DROP " . $this->getConnection()->quoteIdentifier('language'));
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
