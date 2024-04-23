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

class V1Dot13Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-23 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE product RENAME COLUMN uvp TO rrp");
        } else {
            $this->exec("ALTER TABLE product CHANGE uvp rrp DOUBLE PRECISION DEFAULT '0'");
        }

        $path = "custom/Espo/Custom/Resources/layouts/Product/";
        $files = scandir($path);
        if (is_array($files)) {
            $files = array_diff($files, array('.', '..'));

            foreach ($files as $file) {
                $contents = file_get_contents($path . $file);
                $contents = str_replace('"uvp"', '"rrp"', $contents);
                file_put_contents($path . $file, $contents);
            }
        }

        $path = "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"uvp"', '"rrp"', $contents);
            file_put_contents($path, $contents);
        }
    }

    public function down(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE product RENAME COLUMN rrp TO uvp");
        } else {
            $this->exec("ALTER TABLE product CHANGE rrp uvp DOUBLE PRECISION DEFAULT '0'");
        }

        $path = "custom/Espo/Custom/Resources/layouts/Product/";
        $files = scandir($path);
        if (is_array($files)) {
            $files = array_diff($files, array('.', '..'));

            foreach ($files as $file) {
                $contents = file_get_contents($path . $file);
                $contents = str_replace('"rrp"', '"uvp"', $contents);
                file_put_contents($path . $file, $contents);
            }
        }

        $path = "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"rrp"', '"uvp"', $contents);
            file_put_contents($path, $contents);
        }
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
