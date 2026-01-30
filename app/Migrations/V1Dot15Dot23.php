<?php
/*
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

class V1Dot15Dot23 extends Base
{
    protected string $default = 'draft';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-30 18:00:00');
    }

    public function up(): void
    {
        $this->updateSuctomMetadata();

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('product')) {
            $table = $toSchema->getTable('product');

            if ($table->hasColumn('product_status')) {
                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE product RENAME COLUMN product_status TO status;");
                    $this->exec("ALTER TABLE product ALTER COLUMN status SET DEFAULT '{$this->default}';");
                } else {
                    $this->exec("ALTER TABLE product CHANGE product_status status VARCHAR(255) DEFAULT '{$this->default}';");
                }
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
        }
    }

    protected function updateSuctomMetadata(): void
    {
        $fileName = "data/metadata/entityDefs/Product.json";

        $data = [];
        if (file_exists($fileName)) {
            $data = json_decode(file_get_contents($fileName), true);
        }

        if (isset($data['fields']['productStatus'])) {
            $defs = $data['fields']['productStatus'];
            if (!empty($defs['default'])) {
                $this->default = $defs['default'];
            }

            unset($data['fields']['productStatus']);

            $data['fields']['status'] = $defs;

            file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error($e->getMessage());
        }
    }
}
