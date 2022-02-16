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

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Migration class for version 1.4.8
 */
class V1Dot4Dot8 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        if ($sql = $this->prepareSql("ADD name_%s VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci")) {
            $this->exec($sql);
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        if ($sql = $this->prepareSql("DROP name_%s")) {
            $this->exec($sql);
        }
    }

    /**
     * @param string $cond
     *
     * @return string
     */
    protected function prepareSql(string $cond): string
    {
        $locales = $this->getLocales();
        if (empty($locales)) {
            return '';
        }

        $sql = "ALTER TABLE `attribute_tab` ";
        $localesPart = [];

        foreach ($locales as $locale) {
            $localesPart[] = sprintf($cond,strtolower($locale));
        }

        $localesPart = implode(", ", $localesPart);
        $sql .= $localesPart;

        return $sql;
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

    /**
     * @param string $query
     *
     * @return void
     */
    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
