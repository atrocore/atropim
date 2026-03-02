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

namespace Pim\Seeders;

use Atro\Core\Utils\IdGenerator;
use Atro\Seeders\AbstractSeeder;

class PreviewSeeder extends AbstractSeeder
{
    public function run(): void
    {
        foreach ($this->getPreviewTemplates() as $preview) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->insert('preview_template')
                    ->setValue('id', ':id')
                    ->setValue('deleted', ':false')
                    ->setValue('name', ':name')
                    ->setValue('entity_type', ':entityType')
                    ->setValue('is_active', ':active')
                    ->setValue('template', ':template')
                    ->setParameter('id', $preview['id'] ?? IdGenerator::uuid())
                    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('entityType', $preview['entityType'])
                    ->setParameter('name', $preview['name'])
                    ->setParameter('active', $preview['active'], \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('template', $preview['template'])
                    ->executeStatement();
            } catch (\Throwable $e) {
            }
        }
    }

    public function getPreviewTemplates(): array
    {
        $module = new \ReflectionClass(\Pim\Module::class);
        $templateDir = dirname($module->getFileName()) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;

        return [
            [
                'id'         => 'product_preview',
                'name'       => 'Product preview',
                'template'   => file_get_contents($templateDir . 'Product' . DIRECTORY_SEPARATOR . 'template.twig'),
                'active'     => true,
                'entityType' => 'Product',
            ],
        ];
    }
}