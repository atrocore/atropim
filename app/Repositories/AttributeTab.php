<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
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
            ->set('attribute_tab_id', null)
            ->where('a.attribute_tab_id = :id')
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
}
