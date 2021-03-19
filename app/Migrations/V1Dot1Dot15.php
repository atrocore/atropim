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

use Espo\Core\Utils\Json;
use Treo\Core\Migration\Base;

/**
 * Class V1Dot1Dot15
 */
class V1Dot1Dot15 extends V1Dot1Dot10
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("DROP INDEX IDX_NAME ON `product_attribute_value`");
        $this->execute("ALTER TABLE `product_attribute_value` DROP name");
        $this->execute("ALTER TABLE `product_attribute_value` DROP image_name");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("ALTER TABLE `product_attribute_value` ADD name VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_NAME ON `product_attribute_value` (name, deleted)");
        $this->execute("ALTER TABLE `product_attribute_value` ADD image_name VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
    }
}
