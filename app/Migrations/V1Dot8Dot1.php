<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

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