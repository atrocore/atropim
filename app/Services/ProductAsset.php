<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Relationship;
use Espo\ORM\Entity;

class ProductAsset extends Relationship
{
    protected $mandatorySelectAttributeList = ['isMainImage', 'scope', 'channelId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('assetId')) && !empty($asset = $this->getServiceFactory()->create('Asset')->getEntity($entity->get('assetId')))) {
            $entity->set('fileId', $asset->get('fileId'));
            $entity->set('fileName', $asset->get('fileName'));
            $entity->set('filePathsData', $asset->get('filePathsData'));
            $entity->set('icon', $asset->get('icon'));
        }

        $entity->set('isInherited', false);

        $product = $entity->get('product');
        if (!empty($product)) {
            $parents = $product->get('parents');
            if (!empty($parents[0])) {
                foreach ($parents as $parent) {
                    $productAssets = $parent->get('productAssets');
                    foreach ($productAssets as $productAsset) {
                        if ($productAsset->get('assetId') == $entity->get('assetId')
                            && $productAsset->get('scope') == $entity->get('scope')
                            && $productAsset->get('channelId') == $entity->get('channelId')) {
                            $entity->set('isInherited', true);
                            break 2;
                        }
                    }
                }
            }
        }
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_sortedIds') && !empty($data->_sortedIds)) {
            $this->getRepository()->updateSortOrder($data->_sortedIds);
            return $this->getEntity($id);
        }

        if ($this->isPseudoTransaction()) {
            return parent::updateEntity($id, $data);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::updateEntity($id, $data);
        }

        if (in_array('productAssets', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::updateEntity($id, $data);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionUpdateJobs($id, clone $data);
            $result = parent::updateEntity($id, $data);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function createEntity($attachment)
    {
        if ($this->isPseudoTransaction()) {
            return parent::createEntity($attachment);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::createEntity($attachment);
        }

        if (in_array('productAssets', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::createEntity($attachment);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = parent::createEntity($attachment);
            $this->createPseudoTransactionCreateJobs(clone $attachment);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    public function deleteEntity($id)
    {
        if (!empty($this->simpleRemove)) {
            return parent::deleteEntity($id);
        }

        if ($this->isPseudoTransaction()) {
            return parent::deleteEntity($id);
        }

        if (!$this->getMetadata()->get('scopes.Product.relationInheritance', false)) {
            return parent::deleteEntity($id);
        }

        if (in_array('productAssets', $this->getMetadata()->get('scopes.Product.unInheritedRelations', []))) {
            return parent::deleteEntity($id);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->createPseudoTransactionDeleteJobs($id);
            $result = parent::deleteEntity($id);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        if (!property_exists($data, 'productId')) {
            return;
        }

        $children = $this->getEntityManager()->getRepository('Product')->getChildrenArray($data->productId);
        foreach ($children as $child) {
            $inputData = clone $data;
            $inputData->productId = $child['id'];
            $inputData->productName = $child['name'];
            $transactionId = $this->getPseudoTransactionManager()->pushCreateEntityJob($this->entityType, $inputData, $parentTransactionId);

            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionCreateJobs(clone $inputData, $transactionId);
            }
        }
    }

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($id);

        foreach ($children as $child) {
            $inputData = new \stdClass();

            if (property_exists($data, 'assetId')) {
                $inputData->assetId = $data->assetId;
            }

            if (property_exists($data, 'sorting')) {
                $inputData->sorting = $data->sorting;
            }

            if (property_exists($data, 'tags')) {
                $inputData->tags = $data->tags;
            }

            if (!empty((array)$inputData)) {
                $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $child['id'], $inputData, $parentTransactionId);

                if ($child['childrenCount'] > 0) {
                    $this->createPseudoTransactionUpdateJobs($child['id'], clone $inputData, $transactionId);
                }
            }
        }
    }

    protected function createPseudoTransactionDeleteJobs(string $id, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($id);
        foreach ($children as $child) {
            $transactionId = $this->getPseudoTransactionManager()->pushDeleteEntityJob($this->entityType, $child['id'], $parentTransactionId);

            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionDeleteJobs($child['id'], $transactionId);
            }
        }
    }
}
