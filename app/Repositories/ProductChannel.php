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

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;

class ProductChannel extends Relationship
{
    protected function init()
    {
        parent::init(); 
        $this->addDependency('serviceFactory');
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
       if($entity->isNew()){
           $this->getInjection('serviceFactory')->create('Product')->createChannelProductAttributeValues($entity->get('product'), $entity->get('channelId'));
       }
    }
}