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

class V1Dot15Dot9 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-04 15:00:00');
    }

    public function up(): void
    {
        $seeder = new \Pim\Seeders\PreviewSeeder($this->getConfig(), $this->getConnection());

        foreach ($seeder->getPreviewTemplates() as $previews) {
            if (empty($previews['template']) || empty($previews['id'])) {
                continue;
            }

            try {
                $this->getConnection()->createQueryBuilder()
                    ->update('preview_template')
                    ->set('template', ':template')
                    ->where('id = :id')
                    ->setParameter('template', $previews['template'])
                    ->setParameter('id', $previews['id'])
                    ->executeStatement();
            } catch (\Throwable $e) {}
        }
    }
}
