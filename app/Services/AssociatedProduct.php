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

namespace Pim\Services;

use Atro\Core\Templates\Services\Relation;
use Atro\Entities\File;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

class AssociatedProduct extends Relation
{
    public function prepareEntityForOutput(Entity $entity): void
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($mainProduct = $entity->get('associatingItem')) && !empty($image = $this->getMainImage($mainProduct))) {
            $entity->set('associatingItemImageId', $image->get('id'));
            $entity->set('associatingItemImageName', $image->get('name'));
            $entity->set('associatingItemImagePathsData', $image->getPathsData());
        }

        if (!empty($relatedProduct = $entity->get('associatedItem')) && !empty($image = $this->getMainImage($relatedProduct))) {
            $entity->set('associatedItemImageId', $image->get('id'));
            $entity->set('associatedItemImageName', $image->get('name'));
            $entity->set('associatedItemImagePathsData', $image->getPathsData());
        }
    }

    protected function getMainImage(IEntity $product): ?File
    {
        $mainImage = $this->getEntityManager()->getRepository('ProductFile')->where(['productId' => $product->get('id'), 'isMainImage' => true])->findOne();

        if (!empty($mainImage)) {
            return $mainImage->get('file');
        }

        return null;
    }


}
