<?php

declare(strict_types=1);

namespace Pim\Migrations;

/**
 * Migration class for version 2.12.1
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot12Dot1 extends \Treo\Core\Migration\AbstractMigration
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
