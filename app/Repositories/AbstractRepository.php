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
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

/**
 * Class AbstractRepository
 */
abstract class AbstractRepository extends Base
{
    public const CODE_PATTERN = '/^[\p{Ll}0-9_]*$/u';

    /**
     * @var string
     */
    protected $ownership;

    /**
     * @var string
     */
    protected $ownershipRelation;

    /**
     * @var string
     */
    protected $assignedUserOwnership;

    /**
     * @var string
     */
    protected $ownerUserOwnership;

    /**
     * @var string
     */
    protected $teamsOwnership;

    /**
     * @var int
     */
    public const RECORDS_PER_QUERY = 500;

    /**
     * @param string $id
     * @param array  $teamsIds
     */
    public function changeMultilangTeams(string $id, string $entityType, array $teamsIds)
    {
        $sql = ["DELETE FROM entity_team WHERE entity_type='{$entityType}' AND entity_id='$id'"];
        foreach ($teamsIds as $teamId) {
            $sql[] = "INSERT INTO entity_team (entity_id, team_id, entity_type) VALUES ('$id', '$teamId', '{$entityType}')";
        }
        $this->getEntityManager()->nativeQuery(implode(";", $sql));
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isImage(string $name): bool
    {
        $parts = explode('.', $name);
        $fileExt = strtolower(array_pop($parts));

        return in_array($fileExt, $this->getMetadata()->get('dam.image.extensions', []));
    }

    /**
     * @param Entity $entity
     * @param array  $result
     *
     * @return array
     */
    protected function prepareAssets(Entity $entity, array $result): array
    {
        $channelsIds = array_column($result, 'channel');

        $channels = [];
        if (!empty($channelsIds)) {
            $dbChannels = $this->getEntityManager()->getRepository('Channel')->select(['id', 'name'])->where(['id' => $channelsIds])->find()->toArray();
            $channels = array_column($dbChannels, 'name', 'id');
        }

        if ($entity->getEntityName() == 'Product') {
            $productChannelsIds = array_column($entity->get('channels')->toArray(), 'id');

            if (!is_array($data = $entity->getDataField('mainImages'))) {
                $data = [];
            }
        } else {
            $data = [];
        }

        foreach ($result as $k => $v) {
            $result[$k]['entityName'] = $entity->getEntityType();
            $result[$k]['entityId'] = $entity->get('id');
            $result[$k]['scope'] = 'Global';
            $result[$k]['channelId'] = null;
            $result[$k]['channelName'] = null;
            if (!empty($v['channel']) && !empty($channels[$v['channel']])) {
                $result[$k]['scope'] = 'Channel';
                $result[$k]['channelId'] = $v['channel'];
                $result[$k]['channelName'] = $channels[$v['channel']];
            }

            if ($this->isImage($result[$k]['fileName'])) {
                $result[$k]['isImage'] = true;
                if ($result[$k]['fileId'] === $entity->get('imageId')) {
                    $result[$k]['isMainImage'] = true;
                    $result[$k]['isGlobalMainImage'] = true;
                } else {
                    if (isset($data[$result[$k]['id']])) {
                        $assetChannels = $data[$result[$k]['id']];

                        if ($result[$k]['scope'] == 'Channel') {
                            $result[$k]['isMainImage'] = true;
                        } elseif (isset($productChannelsIds)) {
                            $result[$k]['channels'] = array_values(array_intersect($productChannelsIds, $assetChannels));
                        }
                    } else {
                        $result[$k]['isMainImage'] = false;
                        $result[$k]['channels'] = [];
                    }
                }
            }

            $result[$k]['channel'] = '-';

            if (!empty($result[$k]['channelId'])) {
                $result[$k]['id'] = $result[$k]['id'] . '_' . (string)$result[$k]['channelId'];
            }
        }

        return $result;
    }

    /**
     * @param Entity        $entity
     * @param Entity|string $foreign
     * @param array         $options
     *
     * @throws Error
     */
    protected function afterUnrelateAssets($entity, $foreign, $options): void
    {
        if (!in_array($entity->getEntityType(), ['Product', 'Category'])) {
            return;
        }

        if (is_string($foreign)) {
            $foreign = $this->getEntityManager()->getEntity('Asset', $foreign);
        }

        if ($entity->get('imageId') === $foreign->get('fileId')) {
            $entity->set('imageId', null);
            $this->getEntityManager()->saveEntity($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param string $config
     */
    protected function setInheritedOwnershipUser(Entity $entity, string $field, string $config)
    {
        $table = Util::toUnderScore($this->ownershipRelation);
        $relatedField = Util::toUnderScore($entity->getEntityType()) . '_id';
        $underscoreField = Util::toUnderScore($field);

        if ($config == $this->ownership) {
            $sql
                = "UPDATE {$table} SET {$underscoreField}_id = '{$entity->get($field . 'Id')}' WHERE {$relatedField} = '{$entity->id}' AND is_inherit_{$underscoreField} = 1 AND deleted = 0;";
            $this->getEntityManager()->nativeQuery($sql);
        }
    }

    /**
     * @param Entity $entity
     * @param array  $teamsIds
     */
    public function setInheritedOwnershipTeams(Entity $entity, array $teamsIds, string $locale = '')
    {
        $teamsOwnership = $this->getConfig()->get($this->teamsOwnership, '');

        if ($teamsOwnership == $this->ownership) {
            // get related entities ids that must inherit teams
            $table = Util::toUnderScore($this->ownershipRelation);
            $relatedTable = Util::toUnderScore($entity->getEntityType());
            $inheritedField = !empty($locale) ? 'is_inherit_teams_' . $locale : 'is_inherit_teams';

            $ids = $this
                ->getEntityManager()
                ->nativeQuery(
                    "SELECT id 
                            FROM {$table}
                            WHERE {$relatedTable}_id = '{$entity->id}' AND {$inheritedField} = 1 AND deleted = 0;"
                )
                ->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $ids = array_map(function ($id) use ($locale) {
                    return !empty($locale) ? $id . '~' . $locale : $id;
                }, $ids);
                $preparedIds = implode("','", $ids);

                // delete old entity teams
                $sql = "DELETE entity_team
                            FROM entity_team 
                            WHERE entity_id IN ('{$preparedIds}') AND entity_type = '{$this->ownershipRelation}';";
                $this
                    ->getEntityManager()
                    ->nativeQuery($sql);

                // insert new teams to entities
                $count = 0;
                $sql = '';
                foreach ($ids as $key => $id) {
                    foreach ($teamsIds as $k => $teamId) {
                        $sql .= "INSERT INTO entity_team SET entity_id = '{$id}', entity_type = '{$this->ownershipRelation}', team_id = '{$teamId}';";
                        $count++;

                        if ($count == self::RECORDS_PER_QUERY || ($key == count($ids) - 1 && $k == count($teamsIds) - 1)) {
                            $this->getEntityManager()->nativeQuery($sql);
                            $count = 0;
                            $sql = '';
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function setInheritedOwnership(Entity $entity)
    {
        if ($entity->isAttributeChanged('assignedUserId')) {
            $this->setInheritedOwnershipUser($entity, 'assignedUser', $this->getConfig()->get($this->assignedUserOwnership, ''));
        }

        if ($entity->isAttributeChanged('ownerUserId')) {
            $this->setInheritedOwnershipUser($entity, 'ownerUser', $this->getConfig()->get($this->ownerUserOwnership, ''));
        }

        if ($entity->isAttributeChanged('teamsIds')) {
            $teamsIds = is_array($entity->get('teamsIds')) ? $entity->get('teamsIds') : [];
            $this->setInheritedOwnershipTeams($entity, $teamsIds);
        }
    }

    /**
     * @param Entity      $entity
     * @param string      $target
     * @param string|null $config
     *
     */
    protected function inheritOwnership(Entity $entity, string $target, ?string $config)
    {
        if (!empty($config)) {
            $inheritedEntity = $this->getInheritedEntity($entity, $config);

            if ($inheritedEntity) {
                if ($target == 'teams') {
                    if (isset($entity->locale)) {
                        $separator = \Pim\Services\ProductAttributeValue::LOCALE_IN_ID_SEPARATOR;
                        $entityId = $entity->id . $separator . $entity->locale;
                        $inheritedId = $inheritedEntity->getEntityType() == 'Attribute' ? $inheritedEntity->id . $separator . $entity->locale : $inheritedEntity->id;
                    } else {
                        $entityId = $entity->id;
                        $inheritedId = $inheritedEntity->id;
                    }
                    $this->getEntityManager()->nativeQuery("DELETE FROM entity_team WHERE entity_id = '{$entityId}'");

                    $sql = "SELECT team_id 
                            FROM entity_team 
                            WHERE entity_type = '{$inheritedEntity->getEntityType()}'
                                AND entity_id = '{$inheritedId}' AND deleted = 0";
                    $teamsIds = $this->getEntityManager()->nativeQuery($sql)->fetchAll(\PDO::FETCH_COLUMN);

                    if (!empty($teamsIds)) {
                        $sql = "";

                        foreach ($teamsIds as $teamId) {
                            $sql .= "INSERT INTO entity_team SET entity_id = '{$entityId}', entity_type = '{$entity->getEntityType()}', team_id = '{$teamId}';";
                        }

                        $this->getEntityManager()->nativeQuery($sql);
                    }
                } else {
                    if (isset($entity->locale)) {
                        $inheritedField = $inheritedEntity->getEntityType() == 'Attribute' ? $target . Util::toCamelCase(strtolower($entity->locale), '_', true) : $target;
                        $target .= Util::toCamelCase(strtolower($entity->locale), '_', true);
                    } else {
                        $inheritedField = $target;
                    }
                    $entity->set($target . 'Id', $inheritedEntity->get($inheritedField . 'Id'));
                    $entity->set($target . 'Name', $inheritedEntity->get($inheritedField . 'Name'));
                }

                $this->getEntityManager()->saveEntity($entity, ['skipAll' => true]);
            }
        }
    }

    /**
     * @param Entity $entity
     * @param string $config
     *
     * @return Entity|null
     */
    protected function getInheritedEntity(Entity $entity, string $config): ?Entity
    {
        return null;
    }
}
