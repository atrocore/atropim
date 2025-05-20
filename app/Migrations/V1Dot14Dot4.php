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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-16 10:00:00');
    }

    public function up(): void
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('attribute_tab')
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $res = Util::arrayKeysToCamelCase($res);

        $result = [];
        foreach ($res as $item) {
            $result[$item['id']] = array_merge($item, ['code' => $item['id'], 'sortOrder' => 0]);
        }

        @mkdir('data/reference-data');
        file_put_contents('data/reference-data/AttributePanel.json', json_encode($result));

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE attribute rename COLUMN attribute_tab_id TO attribute_panel_id");
        } else {
            $this->exec("ALTER TABLE attribute CHANGE attribute_tab_id attribute_panel_id varchar(36) null");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
