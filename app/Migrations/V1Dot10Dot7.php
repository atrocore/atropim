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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot7 extends Base
{
    public function up(): void
    {
        //  "name": "productAssets" to  "name": "assets" in custom
    }

    public function down(): void
    {
    }
}
