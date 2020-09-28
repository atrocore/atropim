<?php
declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Treo\Core\Utils\Util;

/**
 * Class ProductFamilyAttribute
 *
 * @author r.ratsun@gmail.com
 */
class ProductFamilyAttribute extends Base
{
    /**
     * @var array
     */
    private $sqlItems = [];

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        // exit
        if (!empty($options['skipValidation'])) {
            return true;
        }

        // is valid
        $this->isValid($entity);

        // clearing channels ids
        if ($entity->get('scope') == 'Global') {
            $entity->set('channelsIds', []);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, array $options = [])
    {
        // update product attribute values
        $this->updateProductAttributeValues($entity);

        parent::afterSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterRemove(Entity $entity, array $options = [])
    {
        $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->removeCollectionByProductFamilyAttribute($entity->get('id'));

        parent::afterRemove($entity, $options);
    }

    /**
     * @param Entity $entity
     *
     * @throws BadRequest
     */
    protected function isValid(Entity $entity): void
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('attributeId')) {
            throw new BadRequest($this->exception('Product family attribute cannot be changed'));
        }

        if (empty($entity->get('productFamilyId')) || empty($entity->get('attributeId'))) {
            throw new BadRequest($this->exception('ProductFamily and Attribute cannot be empty'));
        }

        if (!$this->isUnique($entity)) {
            throw new BadRequest($this->exception('Such record already exists'));
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        // prepare count
        $item = null;

        if ($entity->get('scope') == 'Global') {
            $item = $this
                ->getEntityManager()
                ->getRepository('ProductFamilyAttribute')
                ->select(['id'])
                ->where(
                    [
                        'id!='            => $entity->get('id'),
                        'productFamilyId' => $entity->get('productFamilyId'),
                        'attributeId'     => $entity->get('attributeId'),
                        'scope'           => 'Global',
                    ]
                )
                ->findOne();
        } elseif ($entity->get('scope') == 'Channel') {
            $item = $this
                ->getEntityManager()
                ->getRepository('ProductFamilyAttribute')
                ->distinct()
                ->select(['id'])
                ->join('channels')
                ->where(
                    [
                        'id!='            => $entity->get('id'),
                        'productFamilyId' => $entity->get('productFamilyId'),
                        'attributeId'     => $entity->get('attributeId'),
                        'scope'           => 'Channel',
                        'channels.id'     => $entity->get('channelsIds'),
                    ]
                )
                ->findOne();
        }

        return empty($item);
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function updateProductAttributeValues(Entity $entity): bool
    {
        // get products ids
        if (empty($productsIds = $entity->get('productFamily')->get('productsIds'))) {
            return true;
        }

        // get channels ids
        $channelsIds = (array)$entity->get('channelsIds');

        // implode channels
        $channels = implode(',', $channelsIds);

        // get already exists
        $exists = $this->getExistsProductAttributeValues($entity, $productsIds);

        // get product family attribute id
        $pfaId = $entity->get('id');

        // get scope
        $scope = $entity->get('scope');

        // get is required param
        $isRequired = (int)$entity->get('isRequired');

        // get attribute id
        $attributeId = $entity->get('attributeId');

        // Link exists records to product family attribute if it needs
        $skipToCreate = [];
        foreach ($exists as $item) {
            // prepare id
            $id = $item['id'];

            if (empty($item['productFamilyAttributeId']) && $item['scope'] == $scope && $item['channels'] == $channels) {
                if ($entity->isNew()) {
                    $skipToCreate[] = $item['productId'];
                    $this->pushSql("UPDATE product_attribute_value SET product_family_attribute_id='$pfaId',is_required=$isRequired WHERE id='$id'");
                } else {
                    $this->pushSql("UPDATE product_attribute_value SET deleted=1 WHERE id='$id'");
                    $this->pushSql("DELETE FROM product_attribute_value_channel WHERE product_attribute_value_id='$id'");
                }
            }
        }

        // Unlink channels from exists records if it needs
        if ($scope == 'Channel') {
            foreach ($exists as $item) {
                // prepare id
                $id = $item['id'];

                if (empty($item['productFamilyAttributeId']) && $item['scope'] == 'Channel' && !empty($item['channels']) && $item['channels'] != $channels) {
                    foreach (explode(',', (string)$item['channels']) as $itemChannel) {
                        if (in_array($itemChannel, $channelsIds)) {
                            $this->pushSql("DELETE FROM product_attribute_value_channel WHERE product_attribute_value_id='$id' AND channel_id='$itemChannel'");
                        }
                    }
                }
            }
        }

        // Update exists records if it needs
        if (!$entity->isNew()) {
            // find ids
            $ids = [];
            foreach ($exists as $item) {
                if ($item['productFamilyAttributeId'] == $pfaId) {
                    $ids[] = $item['id'];
                }
            }
            $this->pushSql("UPDATE product_attribute_value SET is_required=$isRequired,scope='$scope' WHERE product_family_attribute_id='$pfaId' AND deleted=0");
            $this->pushSql("DELETE FROM product_attribute_value_channel WHERE product_attribute_value_id IN ('" . implode("','", $ids) . "')");
            foreach ($ids as $id) {
                foreach ($channelsIds as $channelId) {
                    $this->pushSql("INSERT INTO product_attribute_value_channel (channel_id, product_attribute_value_id) VALUES ('$channelId','$id')");
                }
            }
        }

        // Create a new records if it needs
        if ($entity->isNew()) {
            // prepare data
            $createdById = $entity->get('createdById');
            $ownerUserId = $entity->get('ownerUserId');
            $assignedUserId = $entity->get('assignedUserId');
            $createdAt = $entity->get('createdAt');
            $teamsIds = (array)$entity->get('teamsIds');

            foreach ($productsIds as $productId) {
                if (in_array($productId, $skipToCreate)) {
                    continue 1;
                }

                // generate id
                $id = Util::generateId();

                $this->pushSql(
                    "INSERT INTO product_attribute_value (id,scope,product_id,attribute_id,product_family_attribute_id,created_by_id,created_at,owner_user_id,assigned_user_id,is_required) VALUES ('$id','$scope','$productId','$attributeId','$pfaId','$createdById','$createdAt','$ownerUserId','$assignedUserId',$isRequired)"
                );
                if (!empty($teamsIds)) {
                    foreach ($teamsIds as $teamId) {
                        $this->pushSql("INSERT INTO entity_team (entity_id, team_id, entity_type) VALUES ('$id','$teamId','ProductAttributeValue')");
                    }
                }
                if ($scope == 'Channel') {
                    foreach ($channelsIds as $channelId) {
                        $this->pushSql("INSERT INTO product_attribute_value_channel (channel_id, product_attribute_value_id) VALUES ('$channelId','$id')");
                    }
                }
            }
        }

        // execute sql
        $this->executeSqlItems();

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        $this->addDependency('language');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ProductFamilyAttribute');
    }

    /**
     * @param Entity $entity
     * @param array  $productsIds
     *
     * @return array
     */
    private function getExistsProductAttributeValues(Entity $entity, array $productsIds): array
    {
        // prepare sql
        $sql = "SELECT
                       id,
                       scope,
                       product_id AS productId,
                       (SELECT GROUP_CONCAT(channel_id ORDER BY channel_id ASC) FROM product_attribute_value_channel WHERE product_attribute_value_id=product_attribute_value.id) AS channels,
                       product_family_attribute_id AS productFamilyAttributeId
                FROM product_attribute_value
                WHERE product_id IN ('" . implode("','", $productsIds) . "')
                  AND deleted=0
                  AND attribute_id=:attributeId";

        return $this
            ->getEntityManager()
            ->nativeQuery($sql, ['attributeId' => $entity->get('attributeId')])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param string $sql
     */
    private function pushSql(string $sql): void
    {
        $this->sqlItems[] = $sql;

        if (count($this->sqlItems) > 3000) {
            $this->executeSqlItems();
            $this->sqlItems = [];
        }
    }

    /**
     * Execute SQL items
     */
    private function executeSqlItems(): void
    {
        if (!empty($this->sqlItems)) {
            $this->getEntityManager()->nativeQuery(implode(';', $this->sqlItems));
        }
    }
}
