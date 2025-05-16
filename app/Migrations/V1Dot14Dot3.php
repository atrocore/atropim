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

    protected array $languages = [];

    public function up(): void
    {
        $languageFile = 'data/reference-data/Language.json';
        if (file_exists($languageFile)) {
            $res = @json_decode(file_get_contents($languageFile), true);
            if (!empty($res)) {
                foreach ($res as $k => $row) {
                    if (!empty($row['role']) && $row['role'] === 'additional') {
                        $this->languages[] = $k;
                    }
                }
            }
        }

        echo 'Prepare DB schema' . PHP_EOL;

        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP");
            $this->exec("DROP INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP");

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
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value");
            $this->exec("DROP INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute");

            $this->exec("ALTER TABLE product_attribute_value ADD json_value LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");

            $this->exec("CREATE TABLE variant_specific_product_attribute (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, product_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_UNIQUE_RELATION (deleted, product_id, attribute_id), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_PRODUCT_ID (product_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_ATTRIBUTE_ID (attribute_id, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_CREATED_AT (created_at, deleted), INDEX IDX_VARIANT_SPECIFIC_PRODUCT_ATTRIBUTE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        echo 'Create lingual columns for product_attribute_value' . PHP_EOL;
        foreach ($this->languages as $language) {
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

        echo 'Migrate array type' . PHP_EOL;
        $this->migrateArrayType();

        echo 'Migrate multilingual records' . PHP_EOL;
        $this->migrateMultilang();

        echo 'Migrate channel specific records' . PHP_EOL;
        $this->migrateChannelSpecific();

        echo 'Migrate variant specific parameter for records' . PHP_EOL;
        $this->migrateVariantSpecific();

        $this->exec("ALTER TABLE classification_attribute DROP " . $this->getConnection()->quoteIdentifier('language'));

        echo 'Migrate channel specific for classification attribute' . PHP_EOL;
        $this->migrateChannelSpecificForCa();

        echo 'Migrate default value for classification attribute' . PHP_EOL;
        $this->migrateDefaultValueForClassificationAttributes();

        $this->deletePavDuplicates();
        $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value (deleted, product_id, attribute_id)");

        $this->deleteCaDuplicates();
        $this->exec("CREATE UNIQUE INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute (deleted, classification_id, attribute_id)");

        $this->exec("ALTER TABLE classification ADD entity_id VARCHAR(36) DEFAULT NULL");
        $this->getConnection()->createQueryBuilder()
            ->update('classification')
            ->set('entity_id', ':entityId')
            ->where('deleted=:false')
            ->setParameter('entityId', 'Product')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        echo 'Migrate layouts' . PHP_EOL;
        $this->getConnection()->createQueryBuilder()
            ->delete('layout_relationship_item')
            ->where('name=:pav OR name LIKE :like')
            ->setParameter('pav', 'productAttributeValues')
            ->setParameter('like', 'tab_%')
            ->executeQuery();
    }

    protected function deletePavDuplicates(): void
    {
        while (true) {
            $res = $this->getPDO()
                ->query("SELECT deleted, product_id, attribute_id, COUNT(*) as count FROM product_attribute_value GROUP BY deleted, product_id, attribute_id HAVING COUNT(*) > 1")
                ->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($res[0])) {
                break;
            }

            $item = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('product_attribute_value')
                ->where('product_id=:product_id')
                ->andWhere('deleted=:deleted')
                ->andWhere('attribute_id=:attribute_id')
                ->setParameter('product_id', $res[0]['product_id'])
                ->setParameter('attribute_id', $res[0]['attribute_id'])
                ->setParameter('deleted', $res[0]['deleted'], \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->fetchAssociative();

            if (!empty($item)) {
                echo "Deleted product attribute value duplicate {$item['id']}" . PHP_EOL;
                $this->getConnection()->createQueryBuilder()
                    ->delete('product_attribute_value')
                    ->where('id=:id')
                    ->setParameter('id', $item['id'])
                    ->executeQuery();
            }
        }
    }

    protected function deleteCaDuplicates(): void
    {
        while (true) {
            $res = $this->getPDO()
                ->query("SELECT deleted, classification_id, attribute_id, COUNT(*) as count FROM classification_attribute GROUP BY deleted, classification_id, attribute_id HAVING COUNT(*) > 1")
                ->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($res[0])) {
                break;
            }

            $item = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('classification_attribute')
                ->where('classification_id=:classification_id')
                ->andWhere('deleted=:deleted')
                ->andWhere('attribute_id=:attribute_id')
                ->setParameter('classification_id', $res[0]['classification_id'])
                ->setParameter('attribute_id', $res[0]['attribute_id'])
                ->setParameter('deleted', $res[0]['deleted'], \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->fetchAssociative();

            if (!empty($item)) {
                echo "Deleted classification attribute duplicate {$item['id']}" . PHP_EOL;
                $this->getConnection()->createQueryBuilder()
                    ->delete('classification_attribute')
                    ->where('id=:id')
                    ->setParameter('id', $item['id'])
                    ->executeQuery();
            }
        }
    }

    protected function migrateDefaultValueForClassificationAttributes(): void
    {
        $columns = [
            'bool_value',
            'date_value',
            'datetime_value',
            'int_value',
            'int_value1',
            'float_value',
            'float_value1',
            'varchar_value',
            'text_value',
            'reference_value'
        ];

        foreach ($columns as $column) {
            while (true) {
                try {
                    $res = $this->getConnection()->createQueryBuilder()
                        ->select('ca.*, a.type as attribute_type')
                        ->from('classification_attribute', 'ca')
                        ->leftJoin('ca', 'attribute', 'a', 'a.id=ca.attribute_id AND a.deleted=:false')
                        ->where('ca.deleted=:false')
                        ->andWhere("ca.$column IS NOT NULL")
                        ->setFirstResult(0)
                        ->setMaxResults(5000)
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->fetchAllAssociative();
                } catch (\Throwable $e) {
                    break;
                }

                if (empty($res)) {
                    break;
                }

                foreach ($res as $item) {
                    $this->getConnection()->createQueryBuilder()
                        ->update('classification_attribute')
                        ->set($column, ':null')
                        ->where('id=:id')
                        ->setParameter('id', $item['id'])
                        ->setParameter('null', null, ParameterType::NULL)
                        ->executeQuery();

                    $data = @json_decode($item['data'] ?? '', true);
                    if (!is_array($data)) {
                        $data = [];
                    }

                    if ($column === 'reference_value') {
                        if ($item['attribute_type'] === 'link') {
                            $data['default']['valueId'] = $item[$column];
                        } else {
                            $data['default']['valueUnitId'] = $item[$column];
                        }
                    } elseif ($item['attribute_type'] === 'extensibleMultiEnum') {
                        $data['default']['valueIds'] = $item[$column];
                    } elseif ($item['attribute_type'] === 'rangeInt') {
                        if ($column === 'int_value') {
                            $data['default']['valueFrom'] = $item[$column];
                        } else {
                            $data['default']['valueTo'] = $item[$column];
                        }
                    } elseif ($item['attribute_type'] === 'rangeFloat') {
                        if ($column === 'float_value') {
                            $data['default']['valueFrom'] = $item[$column];
                        } else {
                            $data['default']['valueTo'] = $item[$column];
                        }
                    } else {
                        $data['default']['value'] = $item[$column];
                    }

                    $this->getConnection()->createQueryBuilder()
                        ->update('classification_attribute')
                        ->set('data', ':data')
                        ->where('id=:id')
                        ->setParameter('id', $item['id'])
                        ->setParameter('data', json_encode($data))
                        ->executeQuery();
                }
            }
            $this->exec("ALTER TABLE classification_attribute DROP $column");
        }
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

    protected function migrateChannelSpecificForCa(): void
    {
        while (true) {
            try {
                $res = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from('classification_attribute')
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
                $this->getConnection()->createQueryBuilder()
                    ->update('classification_attribute')
                    ->set('channel_id', ':empty')
                    ->where('id=:id')
                    ->setParameter('empty', '')
                    ->setParameter('id', $item['id'])
                    ->executeQuery();

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
                    ->update('classification_attribute')
                    ->set('attribute_id', ':attributeId')
                    ->where('id=:id')
                    ->setParameter('attributeId', $attributeId)
                    ->setParameter('id', $item['id'])
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE classification_attribute DROP channel_id");
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
                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('channel_id', ':empty')
                    ->where('id=:id')
                    ->setParameter('empty', '')
                    ->setParameter('id', $item['id'])
                    ->executeQuery();

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
                    ->set('attribute_id', ':attributeId')
                    ->where('id=:id')
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
                if (in_array($record['language'], $this->languages)) {
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
                }

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
