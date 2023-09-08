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

use Espo\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot4Dot5 extends Base
{
    public function up(): void
    {
        $query = "SELECT p.id, p.image_id, p.data, a.file_id as attachment_id, a.id as asset_id
                  FROM `product` p 
                      LEFT JOIN product_asset pa on p.id= pa.product_id AND pa.deleted=0
                      LEFT JOIN asset a on a.id= pa.asset_id AND a.deleted=0
                  WHERE p.deleted=0 
                  ORDER BY p.id";

        $products = [];
        foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            $products[$record['id']]['id'] = $record['id'];
            $products[$record['id']]['image_id'] = $record['image_id'];
            $products[$record['id']]['data'] = $record['data'];
            $products[$record['id']]['assets'][$record['asset_id']] = [
                'asset_id'      => $record['asset_id'],
                'attachment_id' => $record['attachment_id'],
            ];
        }

        foreach ($products as $product) {
            $data = @json_decode((string)$product['data'], true);
            if (empty($data)) {
                $data = [];
            }
            $mainImages = isset($data['mainImages']) ? $data['mainImages'] : [];

            $data['mainImages'] = [];
            if (!empty($product['image_id'])) {
                $data['mainImages'][] = [
                    'attachmentId' => $product['image_id'],
                    'scope'        => 'Global',
                    'channelId'    => null
                ];
            }

            foreach ($mainImages as $assetId => $channelsIds) {
                foreach ($channelsIds as $channelId) {
                    if (!isset($product['assets'][$assetId])) {
                        continue 1;
                    }
                    $data['mainImages'][] = [
                        'attachmentId' => $product['assets'][$assetId]['attachment_id'],
                        'scope'        => 'Channel',
                        'channelId'    => $channelId
                    ];
                }
            }

            $jsonData = json_encode($data);

            $this->getPDO()->exec("UPDATE `product` SET data='$jsonData' WHERE id='{$product['id']}'");
        }

        try {
            $this->getPDO()->exec("ALTER TABLE `product` DROP image_id");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
