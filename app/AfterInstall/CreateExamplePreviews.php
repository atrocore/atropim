<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Creates the example preview templates.
 */
class CreateExamplePreviews implements AfterInstallInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSortOrder(): int
    {
        return 30;
    }

    public function run(): void
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
            try {
                $this->connection->createQueryBuilder()
                    ->insert('preview_template')
                    ->setValue('id', ':id')
                    ->setValue('deleted', ':false')
                    ->setValue('name', ':name')
                    ->setValue('entity_type', ':entityType')
                    ->setValue('is_active', ':active')
                    ->setValue('template', ':template')
                    ->setParameter('id', $preview['id'] ?? IdGenerator::uuid())
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('entityType', $preview['entityType'])
                    ->setParameter('name', $preview['name'])
                    ->setParameter('active', $preview['active'], ParameterType::BOOLEAN)
                    ->setParameter('template', file_get_contents($templateDir . $preview['templateFile']))
                    ->executeStatement();
            } catch (\Throwable $e) {
            }
        }
    }
}
