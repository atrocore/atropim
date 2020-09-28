<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Dam\Entities\Asset;
use Dam\Entities\AssetRelation;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Pim\Services\Product as ProductService;
use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class AssetRelationEntity
 * @package Pim\Listeners
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class AssetRelationEntity extends AbstractListener
{
    protected $hasMainImage = ['Product', 'Category'];

    /**
     * @param Event $event
     * @throws BadRequest
     * @throws Error
     */
    public function beforeSave(Event $event)
    {
        $assetRelation = $event->getArgument('entity');
        $asset = $this->getEntityManager()->getEntity('Asset', $assetRelation->get('assetId'));

        $this->validation($assetRelation, $asset);
    }

    /**
     * @param Event $event
     * @throws Error
     */
    public function afterSave(Event $event)
    {
        $assetRelation = $event->getArgument('entity');
        $asset = $this->getEntityManager()->getEntity('Asset', $assetRelation->get('assetId'));
        $isGalleryImage = $asset->get('type') == 'Gallery Image';
        if ($this->isMainGlobalRole($assetRelation, $asset)) {
            $this->clearingRoleForType($assetRelation, $asset->get('type'), 'Main');
            if ($isGalleryImage) {
                $this->updateMainImage($assetRelation, $asset);
            }
        } elseif (!$this->hasMainImageEntity($assetRelation, $isGalleryImage)) {
            $this->updateMainImage($assetRelation, null);
        }
    }

    /**
     * @param Event $event
     * @throws Error
     */
    public function afterRemove(Event $event)
    {
        $assetRelation = $event->getArgument('entity');
        $asset = $this->getEntityManager()->getEntity('Asset', $assetRelation->get('assetId'));
        if ($this->isMainGlobalRole($assetRelation, $asset) && $asset->get('type') == 'Gallery Image') {
            $this->updateMainImage($assetRelation, null);
        }
    }

    /**
     * @param AssetRelation $relation
     * @param Asset $asset
     * @return bool
     * @throws BadRequest
     * @throws Error
     */
    protected function validation(AssetRelation $relation, Asset $asset): bool
    {
        $type = (string)$asset->get('type');
        $channelsIds = $this->getChannelsIds($relation);
        if (ProductService::isMainRole($relation) && $relation->get('scope') === 'Channel' && !empty($channelsIds)) {
            //checking for the existence of channels with a role Main
            $channelsCount = $this->countRelation($relation, $type, 'Main', 'Channel', $channelsIds);
            if (!empty($channelsCount)) {
                if(empty($relation->get('name')) && empty($relation->get('entityName'))) {
                    $foreign = $this
                            ->getEntityManager()
                            ->getEntity($relation->get('entityName'), $relation->get('entityId'));
                    $this
                        ->getEntityManager()
                        ->getRepository('AssetRelation')
                        ->deleteLink($asset, $foreign);
                }
                throw new BadRequest($this->exception('assetMainRole'));
            }
        }
        return true;
    }

    /**
     * @param AssetRelation $relation
     * @return array
     */
    protected function getChannelsIds(AssetRelation $relation): array
    {
        $channelsIds  = $relation->get('channelsIds');
        if (empty($channelsIds)) {
            $channelsIds = [];
            foreach ($relation->get('channels')->toArray() as $channel) {
                $channelsIds[] = $channel['id'];
            }
        }

        return $channelsIds;
    }

    /**
     * @param AssetRelation $assetRelation
     * @param Asset $asset
     * @throws Error
     */
    protected function updateMainImage(AssetRelation $assetRelation, ?Asset $asset): void
    {
        if (in_array($assetRelation->get('entityName'), $this->hasMainImage)) {
            $foreign = $this->getEntityManager()
                ->getEntity($assetRelation->get('entityName'), $assetRelation->get('entityId'));
            //prepare image
            $imageId = !empty($asset) ? $asset->get('fileId') : null;
            // update main image if it needs
            if (!empty($foreign) && $imageId != $foreign->get('imageId')) {
                $foreign->set('imageId', $imageId);
                $foreign->keepAttachment = true;
                $this->getEntityManager()->saveEntity($foreign, ['skipAfterSave' => true]);
            }
        }
    }

    /**
     * @param AssetRelation $assetRelation
     * @param string $type
     * @param string $role
     */
    protected function clearingRoleForType(AssetRelation $assetRelation, string $type, string $role): void
    {
        $assetRelations = $this->getAssetRelations($assetRelation, $type, $role, 'Global');
        $sqlUpdate = '';
        foreach ($assetRelations as $relation) {
            $roles = json_decode($relation['role'], true);
            if (is_array($roles) && !is_bool($keyRole = array_search($role, $roles))) {
                unset($roles[$keyRole]);
                $roles = json_encode($roles);
                $sqlUpdate .= "UPDATE asset_relation SET role = '{$roles}' WHERE id = '{$relation['id']}';";
            }
        }
        if (!empty($sqlUpdate)) {
            $this->getEntityManager()->nativeQuery($sqlUpdate);
        }
    }

    /**
     * @param AssetRelation $relation
     * @param string $type
     * @param string $role
     * @param string $scope
     * @param array|null $channelsId
     * @return array
     */
    protected function getAssetRelations(AssetRelation $relation, string $type, string $role, string $scope, array $channelsId = null): array
    {
        $sql = $this->getSqlForAssetRelation($relation, $type, $role, $scope, 'ar.id, ar.role', $channelsId);
        return $this
            ->getEntityManager()
            ->nativeQuery($sql)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param AssetRelation $relation
     * @param string $type
     * @param string $role
     * @param string $scope
     * @param array|null $channelsId
     * @return int
     */
    protected function countRelation(AssetRelation $relation, string $type, string $role, string $scope, array $channelsId = null): int
    {
        $sql = $this->getSqlForAssetRelation($relation, $type, $role, $scope, 'count(*)', $channelsId);
        return (int) $this
            ->getEntityManager()
            ->nativeQuery($sql)
            ->fetchColumn();
    }

    /**
     * @param AssetRelation $relation
     * @param string $type
     * @param string $role
     * @param string $scope
     * @param string $select
     * @param array|null $channelsId
     * @return string
     */
    protected function getSqlForAssetRelation(AssetRelation $relation, string $type, string $role, string $scope, string $select, array $channelsId = null): string
    {
        $sql = "SELECT {$select}
                    FROM asset_relation ar
                         LEFT JOIN asset a ON ar.asset_id = a.id
                         LEFT JOIN asset_relation_channel arc ON arc.asset_relation_id = ar.id AND arc.deleted = 0
                    WHERE ar.entity_id = '{$relation->get('entityId')}'
                        AND ar.entity_name = '{$relation->get('entityName')}'
                        AND ar.role LIKE '%\"{$role}\"%'
                        AND a.type = '{$type}'
                        AND ar.scope = '{$scope}'
                        AND ar.id <> '{$relation->get('id')}'
                        AND ar.deleted = '0'";
        if (!empty($channelsId)) {
            $channelsId = "'" . implode("','", $channelsId) . "'";
            $sql .= " AND arc.channel_id IN ({$channelsId})";
        }

        return $sql;
    }

    /**
     * @param AssetRelation $assetRelation
     * @param $asset
     * @return bool
     */
    protected function isMainGlobalRole(AssetRelation $assetRelation, $asset): bool
    {
        return
            !empty($asset)
            && $assetRelation->get('scope') == 'Global'
            && ProductService::isMainRole($assetRelation);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getContainer()->get('language')->translate($key, 'exceptions', 'AssetRelation');
    }

    /**
     * @param AssetRelation $assetRelation
     * @param bool $isGalleryImage
     * @return bool
     */
    protected function hasMainImageEntity(AssetRelation $assetRelation, bool $isGalleryImage): bool
    {
        return $isGalleryImage
                && $this->countRelation($assetRelation, 'Gallery Image', 'Main', 'Global') > 0;
    }
}
