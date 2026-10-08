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

class V1Dot16Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-08-28 12:00:00');
    }

    public function up(): void
    {
        $connection = $this->getDbal();

        try {
            $users = $connection
                ->createQueryBuilder()
                ->select('id, dashboard_layout')
                ->from($connection->quoteIdentifier('user'))
                ->where('dashboard_layout LIKE :dashlet')
                ->setParameter('dashlet', '%"ProductTypes"%')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($users as $user) {
            $dashboardLayouts = @json_decode($user['dashboard_layout'], true);

            if (!empty($dashboardLayouts) && is_array($dashboardLayouts)) {
                foreach ($dashboardLayouts as $k => $dashboardLayout) {
                    if (isset($dashboardLayout['layout']) && is_array($dashboardLayout['layout'])) {
                        $key = array_search('ProductTypes', array_column($dashboardLayout['layout'], 'name'));

                        if ($key !== false) {
                            array_splice($dashboardLayout['layout'], $key, 1);
                            $dashboardLayouts[$k] = $dashboardLayout;
                        }
                    }
                }

                try {
                    $connection
                        ->createQueryBuilder()
                        ->update($connection->quoteIdentifier('user'))
                        ->set('dashboard_layout', ':layout')
                        ->where('id=:id')
                        ->setParameter('layout', json_encode($dashboardLayouts))
                        ->setParameter('id', $user['id'])
                        ->executeQuery();
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
