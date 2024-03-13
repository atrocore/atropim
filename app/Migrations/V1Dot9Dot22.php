<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot9Dot22 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE attribute DROP enum_default");

        $this->exec("DROP INDEX IDX_FLOAT_VALUE1 ON product_attribute_value");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON product_attribute_value (float_value1, deleted)");

        $this->exec("CREATE INDEX IDX_CREATED_AT ON classification_attribute (created_at, deleted)");
        $this->exec("CREATE INDEX IDX_MODIFIED_AT ON classification_attribute (modified_at, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP bool_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD bool_value TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_BOOL_VALUE ON classification_attribute (bool_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP date_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD date_value DATE DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_DATE_VALUE ON classification_attribute (date_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP datetime_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD datetime_value DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_DATETIME_VALUE ON classification_attribute (datetime_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP int_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD int_value INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_INT_VALUE ON classification_attribute (int_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP int_value1");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD int_value1 INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_INT_VALUE1 ON classification_attribute (int_value1, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP float_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD float_value DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE ON classification_attribute (float_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP float_value1");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD float_value1 DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON classification_attribute (float_value1, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP varchar_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD varchar_value VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_VARCHAR_VALUE ON classification_attribute (varchar_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP text_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD text_value LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_TEXT_VALUE ON classification_attribute (text_value(500), deleted)");

        $this->updateComposer('atrocore/pim', '^1.9.22');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
