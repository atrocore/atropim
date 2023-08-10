<?php

namespace Pim\Migrations;

use Treo\Core\Migration\Base;
class V1Dot9Dot24 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE channel ADD type VARCHAR(255) DEFAULT 'general' COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE channel DROP type");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}