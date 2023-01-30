<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    public function up(): void
    {
        while (!empty($pfaId = $this->getDuplicatePfa())) {
            $this->exec("DELETE FROM product_family_attribute WHERE id='$pfaId'", false);
        }

        $this->exec("DELETE FROM product_attribute_value WHERE attribute_type IN ('enum', 'multiEnum') AND language!='main'");
        $this->exec("UPDATE attribute SET is_multilang=0 WHERE attribute.type IN ('enum', 'multiEnum')");

        $attributes = $this->getSchema()->getConnection()->createQueryBuilder()
            ->select('a.*')
            ->from('attribute', 'a')
            ->andWhere('a.deleted=0')
            ->andWhere('a.type=:enum OR a.type=:multiEnum')->setParameter('enum', 'enum')->setParameter('multiEnum', 'multiEnum')
            ->fetchAllAssociative();

        while (!empty($attributes)) {
            $attribute = array_shift($attributes);

            $typeValue = @json_decode((string)$attribute['type_value']);
            if (empty($typeValue)) {
                continue;
            }
            $typeValueIds = @json_decode((string)$attribute['type_value_ids']);
            if (empty($typeValueIds)) {
                $typeValueIds = array_keys($typeValue);
                $this->exec("UPDATE attribute SET type_value_ids='" . json_encode($typeValueIds) . "' WHERE id='{$attribute['id']}'", false);
            }

            if ($attribute['type'] === 'enum') {
                foreach ($typeValue as $k => $v) {
                    $was = $this->getPDO()->quote($v);
                    $became = $this->getPDO()->quote($typeValueIds[$k]);

                    $this->exec(
                        "UPDATE product_attribute_value SET varchar_value=$became WHERE attribute_type='enum' AND attribute_id='{$attribute['id']}' AND varchar_value=$was", false
                    );
                }
            }

            if ($attribute['type'] === 'multiEnum') {
                $pavs = $this->getSchema()->getConnection()->createQueryBuilder()
                    ->select('pav.*')
                    ->from('product_attribute_value', 'pav')
                    ->andWhere('pav.deleted=0')
                    ->andWhere('pav.attribute_id=:attributeId')->setParameter('attributeId', $attribute['id'])
                    ->fetchAllAssociative();

                while (!empty($pavs)) {
                    $pav = array_shift($pavs);

                    $values = @json_decode((string)$pav['text_value']);
                    if (empty($values)) {
                        $values = [];
                    }

                    $valuesIds = [];
                    foreach ($values as $value) {
                        $key = array_search($value, $typeValue);
                        if ($key !== false) {
                            $valuesIds[] = $typeValueIds[$key];
                        }
                    }

                    $textValue = json_encode($valuesIds);

                    $this->exec("UPDATE product_attribute_value SET text_value='$textValue' WHERE id='{$pav['id']}'", false);
                }
            }

            if ($attribute['type'] === 'enum' && !empty($attribute['enum_default'])) {
                $defaultKey = array_search($attribute['enum_default'], $typeValue);
                if ($defaultKey !== false) {
                    $this->exec("UPDATE attribute SET enum_default='{$typeValueIds[$defaultKey]}' WHERE id='{$attribute['id']}'", false);
                }
            }
        }

        $this->exec("ALTER TABLE product_family_attribute ADD language VARCHAR(255) DEFAULT 'main' COLLATE utf8mb4_unicode_ci");

        $this->exec("UPDATE product_family_attribute SET channel_id='' WHERE channel_id IS NULL");
        $this->exec("DELETE FROM product_family_attribute WHERE deleted=1");

        $this->exec(
            "CREATE UNIQUE INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD04DB71B5EB3B4E33 ON product_family_attribute (product_family_id, attribute_id, scope, channel_id, language, deleted)",
            false
        );

        if ($this->getConfig()->get('isMultilangActive', false)) {
            $records = $this->getSchema()->getConnection()->createQueryBuilder()
                ->select('pfa.*')
                ->from('product_family_attribute', 'pfa')
                ->leftJoin('pfa', 'attribute', 'a', 'pfa.attribute_id=a.id')
                ->where('pfa.deleted=0')
                ->andWhere('a.deleted=0')
                ->andWhere('a.is_multilang=1')
                ->fetchAllAssociative();

            if (!empty($records)) {
                $container = (new \Espo\Core\Application())->getContainer();
                $auth = new \Espo\Core\Utils\Auth($container);
                $auth->useNoAuth();
                $service = $container->get('serviceFactory')->create('ProductFamilyAttribute');
                foreach ($records as $record) {
                    $attachment = new \stdClass();
                    $attachment->languages = $this->getConfig()->get('inputLanguageList', []);
                    $attachment->productFamilyId = $record['product_family_id'];
                    $attachment->attributeId = $record['attribute_id'];
                    $attachment->isRequired = !empty($record['is_required']);
                    $attachment->scope = $record['scope'];
                    $attachment->channelId = $record['channel_id'];

                    try {
                        $service->createEntity($attachment);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        $this->exec("DELETE FROM job WHERE job.name='CheckProductAttributes'");
        $this->exec("DELETE FROM scheduled_job WHERE scheduled_job.job='CheckProductAttributes'");

        $this->exec("DROP INDEX IDX_MAIN_LANGUAGE_ID ON product_attribute_value");
        $this->exec("ALTER TABLE product_attribute_value DROP main_language_id");

        $this->exec("ALTER TABLE product DROP has_inconsistent_attributes");

        $attributes = $this->getSchema()->getConnection()->createQueryBuilder()
            ->select('a.*')
            ->from('attribute', 'a')
            ->andWhere('a.deleted=0')
            ->andWhere('a.sort_order_in_product IS NULL')
            ->fetchAllAssociative();

        foreach ($attributes as $k => $attribute) {
            $sort = time() + $k;
            $this->exec("UPDATE attribute SET sort_order_in_product=$sort WHERE id='{$attribute['id']}'");
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function getDuplicatePfa()
    {
        return $this
            ->getPDO()
            ->query(
                "SELECT pfa1.id
                     FROM product_family_attribute pfa1
                     JOIN product_family_attribute pfa2 ON pfa1.product_family_id=pfa2.product_family_id AND pfa1.attribute_id=pfa2.attribute_id AND pfa1.scope=pfa2.scope
                     AND pfa1.channel_id=pfa2.channel_id
                     WHERE pfa1.deleted=0
                       AND pfa2.deleted=0
                       AND pfa1.id!=pfa2.id
                     ORDER BY pfa1.id
                     LIMIT 0,1"
            )->fetch(\PDO::FETCH_COLUMN);
    }

    protected function exec(string $query, bool $silent = true): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            if (!$silent) {
                echo $query . PHP_EOL;
                throw $e;
            }
        }
    }
}
