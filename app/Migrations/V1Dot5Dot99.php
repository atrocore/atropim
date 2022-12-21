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

use Doctrine\DBAL\Connection;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Treo\Core\Migration\Base;

class V1Dot5Dot99 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE `product_family_attribute` ADD language VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("UPDATE product_family_attribute SET channel_id='' WHERE channel_id IS NULL");
        $this->exec("DELETE FROM product_family_attribute WHERE deleted=1");

        $this->exec("CREATE UNIQUE INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_family_attribute (product_family_id, attribute_id, scope, channel_id, language, deleted)");

        $connection = $this->getSchema()->getConnection();
        $chanel_records = $connection->createQueryBuilder()
            ->select('id', 'locales')
            ->from('channel')
            ->where('deleted = 0')
            ->fetchAllAssociative();

        $channels = [];
        foreach ($chanel_records as $chanel) {
            $channels[$chanel['id']] = @json_decode($chanel['locales']);
        }

        $records = $connection->createQueryBuilder()
            ->select('id')
            ->from('attribute')
            ->where('is_multilang = :multilang')
            ->setParameter('multilang', 1)
            ->fetchAllAssociative();

        $multilangAttributeIds = array_column($records, 'id');

        $result = $connection->createQueryBuilder()
            ->update('product_family_attribute', 'pfa')
            ->set('pfa.language', ':main')
            ->where('pfa.attribute_id in (:attributeIds)')
            ->setParameter('main', 'main')
            ->setParameter('attributeIds', $multilangAttributeIds, Connection::PARAM_STR_ARRAY)
            ->executeQuery();


        if ($this->getConfig()->get('isMultilangActive', false)) {

            $pfas = $connection->createQueryBuilder()
                ->select('*')
                ->from('product_family_attribute')
                ->where('language = :main')
                ->setParameter('main', 'main')
                ->fetchAllAssociative();


            $locales = $this->getConfig()->get('inputLanguageList', []);
            foreach ($pfas as $pfa) {
                $pfa_locales = $locales;
                if ($pfa['channel_id'] && !empty($channels[$pfa['channel_id']])) {
                    $pfa_locales = $channels[$pfa['channel_id']];
                }
                foreach ($pfa_locales as $locale) {
                    $pfa['id'] = Util::generateId();
                    $pfa['language'] = $locale;

                    $columns = [];
                    foreach ($pfa as $key => $value) {
                        $columns[$key] = ':' . $key;
                    }

                    $connection->createQueryBuilder()
                        ->insert('product_family_attribute')
                        ->values($columns)
                        ->setParameters($pfa)
                        ->executeQuery();
                }
            }
        }

        //            $this->getPDO()->exec("ALTER TABLE `product_attribute_value` DROP main_language_id");
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
