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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Atro\Listeners\AbstractListener;

class SettingsService extends AbstractListener
{
    public function beforeUpdate(Event $event): void
    {
        $data = $event->getArgument('data');

        if (property_exists($data, 'productCanLinkedWithNonLeafCategories') && empty($data->productCanLinkedWithNonLeafCategories)) {
            $this->getEntityManager()->getRepository('Product')->unlinkProductsFromNonLeafCategories();
        }

        $this->deleteMultiLangAttributeOnInputLanguageChange($data, 'ClassificationAttribute');
    }

    protected function deleteMultiLangAttributeOnInputLanguageChange($data, $entityName): void
    {
        if (!isset($data->inputLanguageList)) {
            return;
        }

        /** @var Connection $conn */
        $conn   = $this->getContainer()->get('connection');
        $result = [true];
        $limit  = 30000;
        $offset = 0;

        while (!empty($result)) {
            $table  = Util::toUnderScore($entityName);
            $result = $conn->createQueryBuilder()
                ->from($conn->quoteIdentifier($table))
                ->select('id')
                ->where('language NOT IN (:languages) AND language <> :main')
                ->andWhere('deleted=:false')
                ->setParameter('languages', $data->inputLanguageList, Mapper::getParameterType($data->inputLanguageList))
                ->setParameter('main', 'main', ParameterType::STRING)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            if (!empty($result)) {
                $this->getService($entityName)->massRemove([
                    'ids' => array_column($result, 'id')
                ]);
            }

            if (count($result) < $limit) {
                break;
            }
            $offset += $limit;
        }
    }
}
