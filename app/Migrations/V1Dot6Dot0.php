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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE `product_family_attribute` ADD language VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("UPDATE product_family_attribute SET channel_id='' WHERE channel_id IS NULL");
        $this->exec("DELETE FROM product_family_attribute WHERE deleted=1");

        $this->exec(
            "CREATE UNIQUE INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD04DB71B5EB3B4E33 ON product_family_attribute (product_family_id, attribute_id, scope, channel_id, language, deleted)"
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
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
