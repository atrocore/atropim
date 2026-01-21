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

use Atro\Core\Migration\Base as BaseAlias;

class V1Dot15Dot22 extends BaseAlias
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-21 18:00:00');
    }

    public function up(): void
    {
        $this->moveField();
        $this->removeDashlet();
    }

    protected function moveField(): void
    {
        $fileName = "data/metadata/entityDefs/Product.json";

        $data = [];
        if (file_exists($fileName)) {
            $data = json_decode(file_get_contents($fileName), true);
        }

        if (isset($data['fields']['taskStatus'])) {
            $data['fields']['taskStatus'] = array_merge($this->getDefaultDefs(), $data['fields']['taskStatus']);
        } else {
            $data['fields']['taskStatus'] = $this->getDefaultDefs();
        }

        file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function removeDashlet(): void
    {
        $connection = $this->getConnection();

        try {
            $users = $connection
                ->createQueryBuilder()
                ->select('id, dashboard_layout')
                ->from($connection->quoteIdentifier('user'))
                ->where('dashboard_layout LIKE :dashlet')
                ->setParameter('dashlet', '%"ProductsByTaskStatus"%')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($users as $user) {
            $dashboardLayouts = json_decode($user['dashboard_layout'], true);

            if (is_array($dashboardLayouts)) {
                foreach ($dashboardLayouts as $k => $dashboardLayout) {
                    if (isset($dashboardLayout['layout']) && is_array($dashboardLayout['layout'])) {
                        $key = array_search('ProductsByTaskStatus', array_column($dashboardLayout['layout'], 'name'));

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

    protected function getDefaultDefs(): array
    {
        return [
            'type'          => 'multiEnum',
            'optionColors'  => [],
            'options'       => ['mar', 'tech', 'ass', 'img', 'cat', 'ch', 'pr'],
            'default'       => [],
            'isCustom'      => true
        ];
    }
}
