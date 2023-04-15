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

class V1Dot8Dot1 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE classification ADD owner_user_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("DROP INDEX IDX_OWNER_USER ON classification");
        $this->exec("CREATE INDEX IDX_OWNER_USER_ID ON classification (owner_user_id)");
        $this->exec("CREATE INDEX IDX_OWNER_USER ON classification (owner_user_id, deleted)");

        $this->exec("ALTER TABLE classification ADD assigned_user_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("DROP INDEX IDX_ASSIGNED_USER ON classification");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER_ID ON classification (assigned_user_id)");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER ON classification (assigned_user_id, deleted)");

        $this->deleteFile('custom/Espo/Custom/Resources/metadata/clientDefs/ProductFamily.json');
        $this->deleteFile('custom/Espo/Custom/Resources/metadata/entityDefs/ProductFamily.json');
        $this->deleteFile('custom/Espo/Custom/Resources/metadata/scopes/ProductFamily.json');

        $this->deleteFile('custom/Espo/Custom/Resources/metadata/clientDefs/ProductFamilyAttribute.json');
        $this->deleteFile('custom/Espo/Custom/Resources/metadata/scopes/ProductFamilyAttribute.json');
        $this->deleteFile('custom/Espo/Custom/Resources/metadata/entityDefs/ProductFamilyAttribute.json');
    }

    public function down(): void
    {
        throw new \Exception('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function deleteFile(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        unlink($path);
    }
}