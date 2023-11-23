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

namespace Pim\Services;

use Doctrine\DBAL\ParameterType;
use Atro\Core\Templates\Services\Hierarchy;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;

class Category extends Hierarchy
{
    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
        $this->createCategoryAssets($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        parent::afterUpdateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
        $this->createCategoryAssets($entity, $data);
    }

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        // set main images
        if (count($collection) > 0) {
            $conn = $this->getEntityManager()->getConnection();

            $res = $conn->createQueryBuilder()
                ->select('cs.id, a.file_id, a.name, cs.category_id')
                ->from('category_asset', 'cs')
                ->innerJoin('cs', 'asset', 'a', 'a.id=cs.asset_id AND a.deleted=:false')
                ->where('cs.category_id IN (:categoriesIds)')
                ->andWhere('cs.is_main_image = :true')
                ->andWhere('cs.deleted = :false')
                ->setParameter('categoriesIds', array_column($collection->toArray(), 'id'), $conn::PARAM_STR_ARRAY)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($collection as $entity) {
                $entity->set('mainImageId', null);
                $entity->set('mainImageName', null);
                foreach ($res as $item) {
                    if ($item['category_id'] === $entity->get('id')) {
                        $entity->set('mainImageId', $item['file_id']);
                        $entity->set('mainImageName', $item['name']);
                    }
                }
            }
        }

        parent::loadPreviewForCollection($collection);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        Parent::prepareEntityForOutput($entity);

        $channels = $entity->get('channels');
        $channels = !empty($channels) && count($channels) > 0 ? $channels->toArray() : [];

        $entity->set('channelsIds', array_column($channels, 'id'));
        $entity->set('channelsNames', array_column($channels, 'name', 'id'));

        $this->setProductMainImage($entity);
    }

    public function setProductMainImage(Entity $entity): void
    {
        if (!$entity->has('mainImageId')) {
            $entity->set('mainImageId', null);
            $entity->set('mainImageName', null);
            $entity->set('mainImagePathsData', null);

            $productAsset = $this
                ->getEntityManager()
                ->getRepository('CategoryAsset')
                ->where([
                    'categoryId'  => $entity->get('id'),
                    'isMainImage' => true
                ])
                ->findOne();

            if (!empty($productAsset) && !empty($asset = $this->getServiceFactory()->create('Asset')->getEntity($productAsset->get('assetId')))) {
                $entity->set('mainImageId', $asset->get('fileId'));
                $entity->set('mainImageName', $asset->get('fileName'));
                $entity->set('mainImagePathsData', $asset->get('filePathsData'));
            }
        }
    }

    public function findLinkedEntities($id, $link, $params)
    {
        /**
         * For old export feeds. In old export feeds relations to assets is still existing, so we have to returns it.
         */
        if ($link === 'assets') {
            if (empty($params['where'])) {
                $params['where'] = [];
            }

            $categoryAssets = $this
                ->getEntityManager()
                ->getRepository('CategoryAsset')
                ->select(['assetId'])
                ->where(['categoryId' => $id])
                ->find();

            $assetsIds = array_column($categoryAssets->toArray(), 'assetId');
            $assetsIds[] = 'no-such-id';

            $params['where'][] = [
                'type'      => 'equals',
                'attribute' => 'id',
                'value'     => $assetsIds
            ];

            return $this->getServiceFactory()->create('Asset')->findEntities($params);
        }

        $result = Parent::findLinkedEntities($id, $link, $params);

        /**
         * Mark channels as inherited from parent category
         */
        if ($link === 'channels' && $result['total'] > 0 && !empty($channelsIds = $this->getRepository()->getParentChannelsIds($id))) {
            foreach ($result['collection'] as $channel) {
                $channel->set('isInherited', in_array($channel->get('id'), $channelsIds));
            }
        }

        return $result;
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        if (property_exists($data, '_caAssetsIds')) {
            return true;
        }

        return parent::isEntityUpdated($entity, $data);
    }

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (property_exists($data, 'assetsNames')) {
            unset($data->assetsNames);
        }

        if (property_exists($data, 'assetsIds')) {
            $data->_caAssetsIds = $data->assetsIds;
            unset($data->assetsIds);
        }

        if (property_exists($data, 'assetsAddOnlyMode')) {
            $data->_caAddMode = $data->assetsAddOnlyMode;
            unset($data->assetsAddOnlyMode);
        }

        parent::handleInput($data, $id);
    }


    protected function saveMainImage(Entity $entity, $data): void
    {
        if (!property_exists($data, 'mainImageId')) {
            return;
        }

        $asset = $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $data->mainImageId])->findOne();
        if (empty($asset)) {
            return;
        }

        $where = [
            'categoryId' => $entity->get('id'),
            'assetId'    => $asset->get('id')
        ];

        $repository = $this->getEntityManager()->getRepository('CategoryAsset');

        $categoryAsset = $repository->where($where)->findOne();
        if (empty($categoryAsset)) {
            $categoryAsset = $repository->get();
            $categoryAsset->set($where);
        }
        $categoryAsset->set('isMainImage', true);

        $this->getEntityManager()->saveEntity($categoryAsset);
    }

    /**
     * This needs for old import feeds. For import assets from product
     */
    protected function createCategoryAssets(Entity $entity, \stdClass $data): void
    {
        if (!property_exists($data, '_caAssetsIds')) {
            return;
        }

        $assets = $this
            ->getEntityManager()
            ->getRepository('Asset')
            ->where(['id' => $data->_caAssetsIds])
            ->find();

        /** @var CategoryAsset $service */
        $service = $this->getServiceFactory()->create('CategoryAsset');

        foreach ($assets as $asset) {
            $input = new \stdClass();
            $input->categoryId = $entity->get('id');
            $input->assetId = $asset->get('id');

            try {
                $service->createEntity($input);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('CategoryAsset creating failed: ' . $e->getMessage());
            }
        }

        if (!property_exists($data, '_caAddMode') || empty($data->_caAddMode)) {
            $this
                ->getEntityManager()
                ->getRepository('CategoryAsset')
                ->where([
                    'categoryId' => $entity->get('id'),
                    'assetId!='  => array_column($assets->toArray(), 'id')
                ])
                ->removeCollection();
        }
    }
}
