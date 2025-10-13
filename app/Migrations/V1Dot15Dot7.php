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

class V1Dot15Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-13 10:00:00');
    }

    public function up(): void
    {
        $fileName = "data/metadata/clientDefs/ProductSerie.json";
        if (!file_exists($fileName) && $this->getCurrentSchema()->hasTable('product_serie')) {
            file_put_contents($fileName, <<<'EOD'
{
  "controller": "controllers/record",
  "iconClass": "trolley-suitcase"
}
EOD
            );
        }
    }
}
