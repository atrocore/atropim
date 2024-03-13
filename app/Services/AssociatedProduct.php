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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Language;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;

class AssociatedProduct extends Base
{
    protected $mandatorySelectAttributeList = ['backwardAssociatedProductId', 'relatedProductId', 'relatedProductName'];

    public function createEntity($attachment)
    {
        $pdo = $this->getEntityManager()->getPDO();

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $inTransaction = true;
        }

        $this->prepareBackwardAssociationBeforeSave($attachment);

        try {
            $entity = parent::createEntity($attachment);
            if (property_exists($attachment, 'backwardAssociationId') && !empty($attachment->backwardAssociationId)) {
                try {
                    $backwardAttachment = new \stdClass();
                    $backwardAttachment->mainProductId = $attachment->relatedProductId;
                    $backwardAttachment->relatedProductId = $attachment->mainProductId;
                    $backwardAttachment->associationId = $attachment->backwardAssociationId;
                    $backwardAttachment->backwardAssociatedProductId = $entity->get('id');
                    $backwardEntity = parent::createEntity($backwardAttachment);
                    $entity->set('backwardAssociatedProductId', $backwardEntity->get('id'));
                    $this->getRepository()->save($entity, ['skipAll' => true]);
                } catch (\Throwable $e) {
                    $classname = get_class($e);
                    throw new $classname(sprintf($this->getInjection('language')->translate('backwardAssociationError', 'exceptions', 'Product'), $e->getMessage()));
                }
            }

            if (!empty($inTransaction)) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $entity;
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_sortedIds') && !empty($data->_sortedIds)) {
            $this->getRepository()->updateSortOrder($data->_sortedIds);
            return $this->getEntity($id);
        }

        $pdo = $this->getEntityManager()->getPDO();

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $inTransaction = true;
        }

        $this->prepareBackwardAssociationBeforeSave($data);

        try {
            $entity = parent::updateEntity($id, $data);
            try {
                $this->updateBackwardAssociation($entity, $data);
            } catch (\Throwable $e) {
                $classname = get_class($e);
                throw new $classname(sprintf($this->getInjection('language')->translate('backwardAssociationError', 'exceptions', 'Product'), $e->getMessage()));
            }

            if (!empty($inTransaction)) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $entity;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->prepareBackwardAssociation($entity);

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

    public function prepareBackwardAssociation(Entity $entity): void
    {
        $entity->set('backwardAssociationId', null);
        $entity->set('backwardAssociationName', null);

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
    }

    public function updateBackwardAssociation(Entity $entity, \stdClass $data): void
    {
        $backwardAttachment = new \stdClass();

        if (property_exists($data, 'backwardAssociationId') && !Entity::areValuesEqual('varchar', $entity->get('backwardAssociationId'), $data->backwardAssociationId)) {
            if (!empty($entity->get('backwardAssociationId')) && empty($data->backwardAssociationId)) {
                // delete backward association
                $this->getRepository()->deleteFromDb($entity->get('backwardAssociatedProductId'));
                $entity->set('backwardAssociatedProductId', null);
                $this->getRepository()->save($entity, ['skipAll' => true]);
                return;
            } elseif (empty($entity->get('backwardAssociationId')) && !empty($data->backwardAssociationId)) {
                // create backward association
                $backwardAttachment->mainProductId = $entity->get('relatedProductId');
                $backwardAttachment->relatedProductId = $entity->get('mainProductId');
                $backwardAttachment->associationId = $data->backwardAssociationId;
                $backwardAttachment->backwardAssociatedProductId = $entity->get('id');
                $backwardEntity = parent::createEntity($backwardAttachment);
                $entity->set('backwardAssociatedProductId', $backwardEntity->get('id'));
                $this->getRepository()->save($entity, ['skipAll' => true]);
                return;
            } else {
                // update backward association
                $backwardAttachment->associationId = $data->backwardAssociationId;
            }
        }
        if (empty($entity->get('backwardAssociatedProductId'))) {
            return;
        }

        if (property_exists($data, 'mainProductId')) {
            $backwardAttachment->relatedProductId = $data->mainProductId;
        }

        if (property_exists($data, 'relatedProductId')) {
            $backwardAttachment->mainProductId = $data->relatedProductId;
        }

        if (!empty((array)$backwardAttachment)) {
            parent::updateEntity($entity->get('backwardAssociatedProductId'), $backwardAttachment);
        }
    }

    protected function storeEntity(Entity $entity)
    {
        try {
            $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequest($this->getInjection('language')->translate('productAssociationAlreadyExists', 'exceptions', 'Product'));
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
        if ($product->hasRelation('productAssets')) {
            foreach ($product->get('productAssets') as $productAsset) {
                if ($productAsset->get('isMainImage')) {
                    if (!empty($asset = $productAsset->get('asset'))) {
                        return $asset->get('file');
                    }
                }
            }
        }

        return null;
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $this->prepareBackwardAssociation($entity);

        return parent::isEntityUpdated($entity, $data);
    }

    /**
     * @param \stdClass $data
     *
     * @return void
     */
    protected function prepareBackwardAssociationBeforeSave(\stdClass $data): void
    {
        if (property_exists($data, 'backwardAssociation') && !empty($data->backwardAssociation)) {
            $data->backwardAssociationId = $data->backwardAssociation;
        }
    }

    public function removeAssociations($mainProductId, $associationId)
    {
        if (empty($productId)) {
            throw new NotFound();
        }

        $repository = $this->getRepository();
        $where = ['mainProductId' => $mainProductId];
        if (!empty($associationId)) {
            $where['associationId'] = $associationId;
        }
        $repository->where($where)->removeCollection();
        return true;
    }
}
