<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot7Dot6 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM job WHERE job.name='CheckProductAttributes'");
        $this->getPDO()->exec("DELETE FROM scheduled_job WHERE scheduled_job.job='CheckProductAttributes'");
    }

    public function down(): void
    {
    }
}
