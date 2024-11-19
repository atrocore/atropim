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

class V1Dot13Dot53 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-19 12:00:00');
    }

    public function up(): void
    {
        try {
            $preferences = $this->getConnection()->createQueryBuilder()
                ->select('id', 'data')
                ->from('preferences')
                ->fetchAllAssociative();

            foreach ($preferences as $preference) {
                $data = @json_decode($preference['data'], true);
                if (empty($data) || empty($data['dashboardLayout'])) {
                    continue;
                }

                foreach ($data['dashboardLayout'] as &$item) {
                    $layout = [];
                    foreach ($item['layout'] as $item1) {
                        if ($item1['name'] === 'GeneralStatistics') {
                            continue;
                        }
                        $layout[] = $item1;
                    }
                    $item['layout'] = $layout;
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('preferences')
                    ->set('data', ':data')
                    ->where('id= :id')
                    ->setParameter('data', json_encode($data))
                    ->setParameter('id', $preference['id'])
                    ->executeStatement();

            }
        } catch (\Throwable $e) {
        }

    }

    public function down(): void
    {

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
