<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Migration class for version 3.11.23
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class V3Dot11Dot23 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->exec("ALTER TABLE `channel` ADD locales MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->exec("ALTER TABLE `channel` DROP locales");
    }

    /**
     * @param string $sql
     *
     * @return void
     */
    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('Migration of PIM (3.11.23): ' . $sql . ' | ' . $e->getMessage());
        }
    }
}
