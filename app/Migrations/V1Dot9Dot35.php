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

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot9Dot35 extends Base
{
    public function up(): void
    {
        try {
            $attributes = $this->getPDO()->query("SELECT * FROM `attribute` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $attributes = [];
        }

        foreach ($attributes as $attribute) {
            $data = @json_decode((string)$attribute['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            if (!empty($attribute['max_length'])) {
                $data['field']['maxLength'] = $attribute['max_length'];
            }

            $data['field']['countBytesInsteadOfCharacters'] = !empty($attribute['count_bytes_instead_of_characters']);

            $this->exec("UPDATE `attribute` SET data='" . json_encode($data) . "' WHERE id='{$attribute['id']}'");
        }

        $this->exec("ALTER TABLE attribute DROP max_length");
        $this->exec("ALTER TABLE attribute DROP count_bytes_instead_of_characters");

        $this->exec("ALTER TABLE classification_attribute ADD data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonObject)'");

        try {
            $cas = $this->getPDO()->query("SELECT * FROM classification_attribute WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $cas = [];
        }

        foreach ($cas as $ca) {
            $data = @json_decode((string)$ca['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            if (!empty($ca['max_length'])) {
                $data['field']['maxLength'] = $ca['max_length'];
            }

            $data['field']['countBytesInsteadOfCharacters'] = !empty($ca['count_bytes_instead_of_characters']);

            $this->exec("UPDATE classification_attribute SET data='" . json_encode($data) . "' WHERE id='{$ca['id']}'");
        }

        $this->exec("ALTER TABLE classification_attribute DROP max_length");
        $this->exec("ALTER TABLE classification_attribute DROP count_bytes_instead_of_characters");

        $this->updateComposer("atrocore/pim", "^1.9.35");
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