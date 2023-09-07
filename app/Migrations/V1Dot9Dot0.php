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

class V1Dot9Dot0 extends Base
{
    protected array $measures = [];

    public function up(): void
    {
        $this->getPDO()->exec("UPDATE attribute SET type='float' WHERE type='unit'");
        $this->getPDO()->exec("UPDATE product_attribute_value SET attribute_type='float' WHERE attribute_type='unit'");

        $this->exec("ALTER TABLE attribute ADD default_unit VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE attribute ADD measure_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_MEASURE_ID ON attribute (measure_id)");

        $records = $this->getPDO()
            ->query("SELECT * FROM attribute WHERE data LIKE '%\"measure\"%'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            $data = @json_decode($record['data'], true);
            if (empty($data['field']['measure'])) {
                continue;
            }

            $measure = $this->getMeasureByName($data['field']['measure']);
            if (empty($measure)) {
                continue;
            }

            $this->getPDO()->exec("UPDATE attribute SET measure_id='{$measure['id']}' WHERE id='{$record['id']}'");

            if (!empty($data['field']['unitDefaultUnit']) && !empty($measure['units'][$data['field']['unitDefaultUnit']])) {
                $this->getPDO()->exec("UPDATE attribute SET default_unit='{$measure['units'][$data['field']['unitDefaultUnit']]}' WHERE id='{$record['id']}'");
            }

            if (!empty($measure['units'])) {
                foreach ($measure['units'] as $name => $id) {
                    $preparedName = $this->getPDO()->quote($name);
                    $this->getPDO()->exec("UPDATE product_attribute_value SET varchar_value='{$id}' WHERE varchar_value=$preparedName AND attribute_id='{$record['id']}'");
                }
            }
        }
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited.');
    }

    protected function getMeasureByName(string $name): array
    {
        if (!isset($this->measures[$name])) {
            $preparedName = $this->getPDO()->quote($name);
            $records = $this->getPDO()
                ->query("SELECT m.id as measureId, u.* FROM measure m LEFT JOIN unit u ON u.measure_id=m.id WHERE m.name=$preparedName AND m.deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);

            $this->measures[$name] = [];
            foreach ($records as $v) {
                $this->measures[$name]['id'] = $v['measureId'];
                if (isset($v['id'])) {
                    $this->measures[$name]['units'][$v['name']] = $v['id'];
                }
            }
        }

        return $this->measures[$name];
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
