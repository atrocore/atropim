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

use Treo\Core\Migration\Base;

/**
 * Class V1Dot1Dot8
 *
 * @package Pim\Migrations
 */
class V1Dot1Dot8 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("ALTER TABLE `product` ADD is_inherit_assigned_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_owner_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_teams TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `product_attribute_value` ADD is_inherit_assigned_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_owner_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_teams TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");

        foreach ($this->getLocales() as $locale) {
            $locale = strtolower($locale);
            $this->execute("ALTER TABLE `product_attribute_value` ADD is_inherit_assigned_user_{$locale} TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_owner_user_{$locale} TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_teams_{$locale} TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }

    /**
     * @return array
     */
    protected function getLocales(): array
    {
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            return $this->getConfig()->get('inputLanguageList', []);
        }

        return [];
    }
}
