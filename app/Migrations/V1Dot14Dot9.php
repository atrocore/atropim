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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot9 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-03 12:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('preview_template')
            ->set('is_active', ':false')
            ->where('deleted = :false')
            ->andWhere('entity_type = :entityType')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('entityType', 'Product')
            ->executeStatement();

        self::createExamplePreviews($this->getConnection());
    }

    public static function createExamplePreviews(Connection $connection): void
    {
        $module = new \ReflectionClass(\Pim\Module::class);
        $templateDir = dirname($module->getFileName()) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        $previews = [
            [
                'id'           => 'product_preview',
                'name'         => 'Product preview',
                'templateFile' => 'Product' . DIRECTORY_SEPARATOR . 'template.twig',
                'active'       => true,
                'entityType'   => 'Product'
            ]
        ];

        foreach ($previews as $preview) {
            $connection->createQueryBuilder()
                ->insert('preview_template')
                ->setValue('id', ':id')
                ->setValue('deleted', ':false')
                ->setValue('name', ':name')
                ->setValue('entity_type', ':entityType')
                ->setValue('is_active', ':active')
                ->setValue('template', ':template')
                ->setParameter('id', $preview['id'] ?? Util::generateId())
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->setParameter('entityType', $preview['entityType'])
                ->setParameter('name', $preview['name'])
                ->setParameter('active', $preview['active'], \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->setParameter('template', file_get_contents($templateDir . $preview['templateFile']))
                ->executeStatement();
        }
    }
}
