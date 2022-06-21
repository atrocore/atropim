<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Services\Base;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;

class AssociatedProduct extends Base
{
    protected $mandatorySelectAttributeList = ['backwardAssociatedProductId'];

    public function createEntity($attachment)
    {
        $entity = parent::createEntity($attachment);

        if (property_exists($attachment, 'backwardAssociationId')) {
            $backwardAttachment = new \stdClass();
            $backwardAttachment->mainProductId = $attachment->relatedProductId;
            $backwardAttachment->mainProductName = $attachment->relatedProductName;
            $backwardAttachment->relatedProductId = $attachment->mainProductId;
            $backwardAttachment->relatedProductName = $attachment->mainProductName;
            $backwardAttachment->associationId = $attachment->backwardAssociationId;
            $backwardAttachment->associationName = $attachment->backwardAssociationName;
            $backwardAttachment->backwardAssociatedProductId = $entity->get('id');

            try {
                $backwardEntity = parent::createEntity($backwardAttachment);
                $entity->set('backwardAssociatedProductId', $backwardEntity->get('id'));
                $this->getRepository()->save($entity, ['skipAll' => true]);
            } catch (\Throwable $e) {
                // ignore errors
            }
        }

        return $entity;
    }

    public function updateEntity($id, $data)
    {
        return parent::updateEntity($id, $data);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('backwardAssociatedProductId'))) {
            $backwardAssociatedProduct = $this->getRepository()
                ->select(['id', 'associationId', 'associationName'])
                ->where(['id' => $entity->get('backwardAssociatedProductId')])
                ->findOne();

            if (!empty($backwardAssociatedProduct)) {
                $entity->set('backwardAssociationId', $backwardAssociatedProduct->get('associationId'));
                $entity->set('backwardAssociationName', $backwardAssociatedProduct->get('associationName'));
            }
        }

        if (!empty($mainProduct = $entity->get('mainProduct')) && !empty($image = $this->getMainImage($mainProduct))) {
            $entity->set('mainProductImageId', $image->get('id'));
            $entity->set('mainProductImageName', $image->get('name'));
            $entity->set('mainProductImagePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($image));
        }

        if (!empty($relatedProduct = $entity->get('relatedProduct')) && !empty($image = $this->getMainImage($relatedProduct))) {
            $entity->set('relatedProductImageId', $image->get('id'));
            $entity->set('relatedProductImageName', $image->get('name'));
            $entity->set('relatedProductImagePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($image));
        }
    }

    protected function storeEntity(Entity $entity)
    {
        try {
            $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
        } catch (\PDOException $e) {
            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $message = $e->getMessage();
                if (preg_match("/SQLSTATE\[23000\]: Integrity constraint violation: 1062 Duplicate entry '(.*)' for key '(.*)'/", $message, $matches) && !empty($matches[2])) {
                    throw new BadRequest($this->getInjection('language')->translate('productAssociationAlreadyExists', 'exceptions', 'Product'));
                }
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @param \Pim\Entities\Product $product
     *
     * @return Entity|null
     */
    protected function getMainImage(\Pim\Entities\Product $product): ?Attachment
    {
        if ($product->hasRelation('assets')) {
            $assets = $product->get('assets');

            foreach ($assets as $asset) {
                if ($asset->get('isMainImage')) {
                    return $asset->get('file');
                }
            }
        }

        return null;
    }
}
