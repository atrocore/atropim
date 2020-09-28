<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 2.6.0
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot6Dot0 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        // drop old trigger
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare("DROP TRIGGER IF EXISTS trigger_before_insert_product_attribute_value");
        $sth->execute();
    }
}
