<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Class V1Dot6Dot1
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class V1Dot6Dot1 extends AbstractMigration
{

    /**
     * Up to current
     */
    public function up(): void
    {
        $this->getContainer()->get('dataManager')->rebuild();

        $queries[] = $this->getQueriesCopyToNewTable('price_unit', 'measuring_unit');
        $queries[] = $this->getCopyColumnQuery('product_type_package', 'price_unit_id', 'measuring_unit_id');

        $this->executeQuery($queries);
    }

    /**
     * Copy data to new tables
     *
     * @param string $tableFrom
     * @param string $tableTo
     *
     * @return string
     */
    protected function getQueriesCopyToNewTable(string $tableFrom, string $tableTo): string
    {

        $fields = $this->getCommonFields($tableFrom, $tableTo);
        $fieldsOld = '`' . implode("`, `", $fields) . '`';
        $fieldsNew = $fieldsOld;

        $sql = "INSERT INTO $tableTo ($fieldsNew)
                    SELECT $fieldsOld FROM $tableFrom 
                    WHERE NOT EXISTS (SELECT id FROM $tableTo WHERE id = $tableFrom.id);";

        return $sql;
    }

    /**
     * Get copy column query
     *
     * @param string $tableName
     * @param string $columnFrom
     * @param string $columnTo
     *
     * @return string
     */
    protected function getCopyColumnQuery(string $tableName, string $columnFrom, string $columnTo): string
    {
        return "UPDATE $tableName SET $columnTo = $columnFrom;";
    }

    /**
     * Execute query
     *
     * @param array $sql
     */
    protected function executeQuery(array $sql)
    {
        $queries = '';

        if (!empty($sql)) {
            /** @var \PDO $pdo */
            $pdo = $this->getEntityManager()->getPDO();

            foreach ($sql as $q) {
                $queries .= $q;
            }

            $sth = $pdo->prepare($queries);
            $sth->execute();
        }
    }

    /**
     * Get common fields
     *
     * @param $table1
     * @param $table2
     *
     * @return array
     */
    protected function getCommonFields($table1, $table2): array
    {
        return array_intersect($this->getEntityFieldsInDB($table1), $this->getEntityFieldsInDB($table2));
    }

    /**
     * Get fields in DB
     *
     * @param string $tableName
     *
     * @return array
     */
    protected function getEntityFieldsInDB(string $tableName): array
    {
        $sql = "DESCRIBE $tableName";
        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        $info = $sth->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($info, 'Field');
    }
}
