<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 3.10.1
 *
 * @author r.ratsun@gmail.com
 */
class V3Dot10Dot1 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        // migrate bundleProduct
        try {
            $this->execute("UPDATE product SET type='simpleProduct' WHERE type='bundleProduct'");
            $this->execute("DROP TABLE product_type_bundle");
        } catch (\PDOException $e) {
        }

        // migrate packageProduct
        try {
            $this->execute("UPDATE product AS p1 SET p1.measuring_unit_id=(SELECT measuring_unit_id FROM product_type_package WHERE package_product_id=p1.id)");
            $this->execute("UPDATE product SET type='simpleProduct' WHERE type='packageProduct'");
            $this->execute("DROP TABLE product_type_package");
        } catch (\PDOException $e) {
        }
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    private function execute(string $sql)
    {
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare($sql);
        $sth->execute();

        return $sth;
    }
}
