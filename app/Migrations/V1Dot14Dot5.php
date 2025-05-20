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
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-20 15:00:00');
    }

    public function up(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('t.*, ca.attribute_id, ca.id as ca_id, ca.data')
                ->from('classification_attribute_extensible_enum_option', 't')
                ->innerJoin('t', 'classification_attribute', 'ca', 'ca.id=t.classification_attribute_id AND ca.deleted=:false')
                ->where('t.deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $row) {
            $data = @json_decode((string)$row['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $allowedOptions = $data['field']['allowedOptions'] ?? [];
            if (!in_array($row['extensible_enum_option_id'], $allowedOptions)) {
                $allowedOptions[] = $row['extensible_enum_option_id'];
            }

            $data['field']['allowedOptions'] = $allowedOptions;

            $this->getConnection()->createQueryBuilder()
                ->update('classification_attribute')
                ->set('data', ':data')
                ->where('id=:id')
                ->setParameter('id', $row['ca_id'])
                ->setParameter('data', json_encode($data))
                ->executeQuery();
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
