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

/**
 * Migration class for version 1.1.41
 */
class V1Dot1Dot41 extends V1Dot1Dot21
{
    public function up(): void
    {
        $this->execute("CREATE INDEX IDX_A3F32100A2F98E47 ON `product_asset` (channel) ");
        $this->execute("CREATE UNIQUE INDEX UNIQ_A3F321004584665A5DA1941A2F98E47A2F98E47 ON `product_asset` (product_id, asset_id, channel)");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX UNIQ_A3F321004584665A5DA1941A2F98E47A2F98E47 ON `product_asset`");
        $this->execute("DROP INDEX IDX_A3F32100A2F98E47 ON `product_asset`");
        $this->execute("CREATE UNIQUE INDEX UNIQ_A3F321005DA19414584665A ON `product_asset` (asset_id, product_id)");
    }
}
