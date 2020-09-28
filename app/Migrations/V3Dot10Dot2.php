<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 3.10.2
 *
 * @author r.ratsun@gmail.com
 */
class V3Dot10Dot2 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        $this->execute("UPDATE associated_product AS ap SET name=(SELECT name FROM association WHERE id=ap.association_id)");
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
