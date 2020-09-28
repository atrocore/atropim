<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 2.3.0
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot3Dot0 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        $this->getConfig()->set('PimTriggers', false);
        $this->getConfig()->save();
    }
}
