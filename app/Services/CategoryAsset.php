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

namespace Pim\Services;

use Espo\ORM\Entity;

class CategoryAsset extends \Atro\Core\Templates\Services\Relationship
{
    protected $mandatorySelectAttributeList = ['isMainImage'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('assetId')) && !empty($asset = $this->getServiceFactory()->create('Asset')->getEntity($entity->get('assetId')))) {
            $entity->set('fileId', $asset->get('fileId'));
            $entity->set('fileName', $asset->get('fileName'));
            $entity->set('filePathsData', $asset->get('filePathsData'));
            $entity->set('icon', $asset->get('icon'));
        }
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_sortedIds') && !empty($data->_sortedIds)) {
            $this->getRepository()->updateSortOrder($data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }
}
