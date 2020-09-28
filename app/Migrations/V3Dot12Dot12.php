<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Migration class for version 3.12.12
 *
 * @author r.ratsun@gmail.com
 */

/**
 * Migration class for version 3.12.12
 *
 * @author Roman Ratsun <r.ratsun@gmail.com>
 */
class V3Dot12Dot12 extends Base
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        $this->exec("ALTER TABLE `category` ADD sort_order INT DEFAULT NULL COLLATE utf8mb4_unicode_ci");

        // get all categories
        $sth = $this->getPDO()->prepare("SELECT id FROM `category` WHERE deleted=0");
        $sth->execute();
        $data = $sth->fetchAll(\PDO::FETCH_COLUMN);

        // update sort order
        $max = 0;
        foreach ($data as $id) {
            $this->getPDO()->exec("UPDATE category SET sort_order=$max WHERE id='$id'");
            $max = $max + 10;
        }
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
        $this->exec("ALTER TABLE `category` DROP sort_order");
    }

    /**
     * @param string $sql
     */
    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('Migration of PIM (3.12.12): ' . $e->getMessage() . ' | ' . $sql);
        }
    }
}
