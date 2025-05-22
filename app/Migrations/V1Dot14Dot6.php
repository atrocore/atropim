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

class V1Dot14Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-22 10:00:00');
    }

    public function up(): void
    {
        self::createDefaultAttributePanel();

        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('attribute'))
            ->set('attribute_panel_id', ':id')
            ->where('attribute_panel_id IS NULL')
            ->setParameter('id', 'attributeValues')
            ->executeQuery();
    }

    public static function createDefaultAttributePanel(): void
    {
        @mkdir('data/reference-data');

        $result = [];
        if (file_exists('data/reference-data/AttributePanel.json')) {
            $result = @json_decode(file_get_contents('data/reference-data/AttributePanel.json'), true);
            if (!is_array($result)) {
                $result = [];
            }
        }

        $result['attributeValues'] = [
            'id'        => 'attributeValues',
            'code'      => 'attributeValues',
            'name'      => 'Attributes',
            'sortOrder' => 0,
            'entityId'  => 'Product'
        ];

        file_put_contents('data/reference-data/AttributePanel.json', json_encode($result));
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
