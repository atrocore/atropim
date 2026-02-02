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

class V1Dot15Dot24 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-31 15:00:00');
    }

    public function up(): void
    {
        $previewId = 'product_preview';

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id', 'template')
                ->from('preview_template')
                ->where('id = :id')
                ->setParameter('id', $previewId)
                ->fetchAssociative();

            if (!empty($res)) {
                $template = $res['template'];
                $template = str_replace(
                    [
                        "<div class=\"item product-status\" {{ editable(product, ['productStatus']) }}>{{ translateOption(product.productStatus, language, 'productStatus', scope: 'Product') }}</div>",
                    ],
                    [
                        "<div class=\"item product-status\" {{ editable(product, ['status']) }}>{{ product.status is not empty ? translateOption(product.status, language, 'status', scope: 'Product') : '' }}</div>",
                    ],
                    $template);

                $this->getConnection()->createQueryBuilder()
                    ->update('preview_template')
                    ->set('template', ':template')
                    ->where('id = :id')
                    ->setParameter('template', $template)
                    ->setParameter('id', $previewId)
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
        }
    }
}
