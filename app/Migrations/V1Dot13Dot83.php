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

class V1Dot13Dot83 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-18 12:00:00');
    }

    public function up(): void
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('product_file')
            ->where("channel_id is not null and channel_id != ''")
            ->fetchOne();

        if (!empty($result)) {
            $path = "data/metadata/entityDefs/ProductFile.json";
            $data = [];
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
            }
            $data['fields']['channel'] = [
                'type'        => 'link',
                'foreignName' => 'name',
                'notNull'     => true,
                'default'     => '',
                'isCustom'    => true
            ];
            $data['links']['channel'] = [
                'type'    => 'belongsTo',
                'entity'  => 'Channel',
                'foreign' => 'productFiles'
            ];
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $path = "data/metadata/entityDefs/Channel.json";
            $data = [];
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
            }
            $data['fields']['productFiles'] = [
                'type'                 => 'linkMultiple',
                'noLoad'               => true,
                'layoutDetailDisabled' => true,
                'massUpdateDisabled'   => true,
                'isCustom'             => true
            ];
            $data['links']['productFiles'] = [
                'type'    => 'hasMany',
                'foreign' => 'channel',
                'entity'  => 'ProductFile'
            ];
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if($this->isPgSQL()){
            $this->execute("DROP INDEX idx_product_file_channel_id;");
            $this->execute("DROP INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION;");
            $this->execute("ALTER TABLE product_file DROP channel_id;");
            $this->execute("CREATE UNIQUE INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION ON product_file (deleted, product_id, file_id)");
        }else{

        }


    }


    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }

}
