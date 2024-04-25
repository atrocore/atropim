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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

/**
 * Class AttributeTab
 */
class AttributeTab extends Base
{
    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->clearCache();
    }

    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->update($connection->quoteIdentifier('attribute'), 'a')
            ->set('attribute_tab_id', ':null')
            ->where('a.attribute_tab_id = :id')
            ->setParameter('null', null)
            ->setParameter('id', $entity->get('id'))
            ->executeQuery();

        parent::afterRemove($entity, $options);

        $this->clearCache();
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function clearCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
    }

    public function getSimplifyTabs(){
        $dataManager = $this->getInjection('dataManager');
        $tabs = $dataManager->getCacheData('attribute_tabs');
        $nameColumns[] = 'name';
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $nameColumns[] = 'name_' . strtolower($language);
            }
        }
        if ($tabs === null) {
            $connection = $this->getEntityManager()->getConnection();
            $columnsForSelect = array_map(function($column){
                return "t.".$column;
            }, $nameColumns);
            try {
                $tabs = $connection->createQueryBuilder()
                    ->select('t.id, '.join(',', $columnsForSelect))
                    ->from($connection->quoteIdentifier('attribute_tab'), 't')
                    ->where('t.deleted = :false')
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $tabs = [];
            }
            $dataManager->setCacheData('attribute_tabs', $tabs);
        }

        return $tabs;
    }
}
