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
        foreach ($this->getLocales() as $locale) {
            $this->exec("ALTER TABLE `attribute_tab` ADD name_" . strtolower($locale) . " VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        foreach ($this->getLocales() as $locale) {
            $this->exec("ALTER TABLE `attribute_tab` DROP name_" . strtolower($locale));
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
