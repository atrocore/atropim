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

class V1Dot3Dot0 extends V1Dot2Dot11
{
    public function up(): void
    {
        $this->execute("DELETE FROM scheduled_job WHERE job='UpdatePfa'");
        $this->execute("DELETE FROM job WHERE name='UpdatePfa'");
    }

    public function down(): void
    {
    }
}
