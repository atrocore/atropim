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
 */
class V1Dot1Dot8 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $locales = $this->getLocales();
        if (empty($locales)) {
            return;
        }

        foreach ($locales as $locale) {
            $this->execute("ALTER TABLE `attribute` ADD owner_user_" . strtolower($locale) . "_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD assigned_user_" . strtolower($locale) . "_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci;");
            $this->execute("CREATE INDEX IDX_ASSIGNED_USER_" . strtoupper($locale) ." ON `attribute` (assigned_user_" . strtolower($locale) . "_id);");
            $this->execute("CREATE INDEX IDX_OWNER_USER_" . strtoupper($locale) ." ON `attribute` (owner_user_" . strtolower($locale) . "_id);");
            $this->execute("ALTER TABLE `product_attribute_value` ADD is_inherit_assigned_user_" . strtolower($locale) . " TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_owner_user_" . strtolower($locale) . " TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD is_inherit_teams_" . strtolower($locale) . " TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci;");
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $locales = $this->getLocales();
        if (empty($locales)) {
            return;
        }

        foreach ($locales as $locale) {
            $this->execute("DROP INDEX IDX_ASSIGNED_USER_" . strtoupper($locale) ." ON `attribute`;");
            $this->execute("DROP INDEX IDX_OWNER_USER_" . strtoupper($locale) ." ON `attribute`;");
            $this->execute("ALTER TABLE `attribute` DROP owner_user_" . strtolower($locale) . "_id, DROP assigned_user_" . strtolower($locale) . "_id;");
            $this->execute("ALTER TABLE `product_attribute_value` DROP is_inherit_assigned_user_" . strtolower($locale) . ", DROP is_inherit_owner_user_" . strtolower($locale) . ", DROP is_inherit_teams_" . strtolower($locale) . ";");
        }
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
