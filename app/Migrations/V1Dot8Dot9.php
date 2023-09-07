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

use Treo\Core\Migration\Base;

class V1Dot8Dot9 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM classification WHERE deleted=1");
        $this->exec("ALTER TABLE classification ADD `release` VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        while (!empty($id = $this->getDuplicate('classification'))) {
            $this->getPDO()->exec("UPDATE classification SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_456BD231EB3B4E339E47031D77153098 ON classification (deleted, `release`, code)");

        $this->getPDO()->exec("DELETE FROM attribute WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('attribute'))) {
            $this->getPDO()->exec("UPDATE attribute SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_FA7AEFFB77153098EB3B4E33 ON attribute (code, deleted)");

        $this->getPDO()->exec("DELETE FROM category WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('category'))) {
            $this->getPDO()->exec("UPDATE category SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_64C19C177153098EB3B4E33 ON category (code, deleted)");

        $this->getPDO()->exec("DELETE FROM association WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('association'))) {
            $this->getPDO()->exec("UPDATE association SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_FD8521CC77153098EB3B4E33 ON association (code, deleted)");

        $this->getPDO()->exec("DELETE FROM channel WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('channel'))) {
            $this->getPDO()->exec("UPDATE channel SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_A2F98E4777153098EB3B4E33 ON channel (code, deleted)");

        $this->getPDO()->exec("DELETE FROM attribute_group WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('attribute_group'))) {
            $this->getPDO()->exec("UPDATE attribute_group SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_8EF8A77377153098EB3B4E33 ON attribute_group (code, deleted)");

        $this->getPDO()->exec("DELETE FROM catalog WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('catalog'))) {
            $this->getPDO()->exec("UPDATE catalog SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_1B2C324777153098EB3B4E33 ON catalog (code, deleted)");

        $this->getPDO()->exec("DELETE FROM brand WHERE deleted=1");
        while (!empty($id = $this->getDuplicate('brand'))) {
            $this->getPDO()->exec("UPDATE brand SET code=NULL WHERE id='$id'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_1C52F95877153098EB3B4E33 ON brand (code, deleted)");
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited!");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function getDuplicate(string $table)
    {
        $query = "SELECT c1.id
                  FROM $table c1
                  JOIN $table c2 ON c1.code=c2.code
                  WHERE c1.deleted=0
                    AND c2.deleted=0
                    AND c1.id!=c2.id
                  ORDER BY c1.id
                  LIMIT 0,1";

        try {
            $res = $this->getPDO()->query($query)->fetch(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return null;
        }

        return $res;
    }
}
