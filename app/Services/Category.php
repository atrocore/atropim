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

namespace Pim\Services;

use Atro\Entities\File;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Templates\Services\Hierarchy;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;

class Category extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['categoryRoute'];

    public function getRoute(string $id): array
    {
        if (empty($category = $this->getRepository()->get($id))) {
            return [];
        }

        if (empty($categoryRoute = $category->get('categoryRoute'))) {
            return [];
        }

        $route = explode('|', $categoryRoute);
        array_shift($route);
        array_pop($route);

        return $route;
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        parent::afterUpdateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
    }

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        // set main images
        if (count($collection) > 0) {
            $conn = $this->getEntityManager()->getConnection();

            $res = $conn->createQueryBuilder()
                ->select('cs.id, a.id as file_id, a.name, cs.category_id')
                ->from('category_file', 'cs')
                ->innerJoin('cs', 'file', 'a', 'a.id=cs.file_id AND a.deleted=:false')
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

            $relEntity = $this
                ->getEntityManager()
                ->getRepository('CategoryFile')
                ->where([
                    'categoryId'  => $entity->get('id'),
                    'isMainImage' => true
                ])
                ->findOne();

            if (!empty($relEntity) && !empty($relEntity->get('fileId'))) {
                /** @var File $file */
                $file = $this->getEntityManager()->getRepository('File')->get($relEntity->get('fileId'));
                if (!empty($file)) {
                    $entity->set('mainImageId', $file->get('id'));
                    $entity->set('mainImageName', $file->get('name'));
                    $entity->set('mainImagePathsData', $file->getPathsData());
                }
            }
        }
    }

    public function findLinkedEntities($id, $link, $params)
    {
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

    protected function saveMainImage(Entity $entity, $data): void
    {
        if (!property_exists($data, 'mainImageId')) {
            return;
        }

        $file = $this->getEntityManager()->getRepository('File')->where(['id' => $data->mainImageId])->findOne();
        if (empty($file)) {
            return;
        }

        $where = [
            'categoryId' => $entity->get('id'),
            'fileId'    => $file->get('id')
        ];

        $repository = $this->getEntityManager()->getRepository('CategoryFile');

        $categoryFile = $repository->where($where)->findOne();
        if (empty($categoryFile)) {
            $categoryFile = $repository->get();
            $categoryFile->set($where);
        }
        $categoryFile->set('isMainImage', true);

        $this->getEntityManager()->saveEntity($categoryFile);
    }
}
