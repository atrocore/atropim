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

class V1Dot13Dot36 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-10-01 08:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE channel_classification (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, channel_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_CHANNEL_CLASSIFICATION_UNIQUE_RELATION ON channel_classification (deleted, channel_id, classification_id);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_CREATED_BY_ID ON channel_classification (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_MODIFIED_BY_ID ON channel_classification (modified_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_CHANNEL_ID ON channel_classification (channel_id, deleted);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_CLASSIFICATION_ID ON channel_classification (classification_id, deleted);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_CREATED_AT ON channel_classification (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_CHANNEL_CLASSIFICATION_MODIFIED_AT ON channel_classification (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE channel_classification (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, channel_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_CHANNEL_CLASSIFICATION_UNIQUE_RELATION (deleted, channel_id, classification_id), INDEX IDX_CHANNEL_CLASSIFICATION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_CHANNEL_CLASSIFICATION_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_CHANNEL_CLASSIFICATION_CHANNEL_ID (channel_id, deleted), INDEX IDX_CHANNEL_CLASSIFICATION_CLASSIFICATION_ID (classification_id, deleted), INDEX IDX_CHANNEL_CLASSIFICATION_CREATED_AT (created_at, deleted), INDEX IDX_CHANNEL_CLASSIFICATION_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }
    }

    public function down(): void
    {
        $this->exec("DROP TABLE channel_classification");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
