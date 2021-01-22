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

use Espo\Core\Exceptions\Error;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Treo\Core\Utils\Util;

/**
 * Class AbstractRepository
 */
abstract class AbstractRepository extends Base
{
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
    protected const RECORDS_PER_QUERY = 500;

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
            $result[$k]['channel'] = '-';
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
     */
    protected function setInheritedOnwership(Entity $entity)
    {
        if ($entity->isAttributeChanged('assignedUserId')) {
            $config = $this->getConfig()->get($this->assignedUserOwnership, '');
            $table = Util::toUnderScore($this->ownershipRelation);
            $relatedField = Util::toUnderScore($entity->getEntityType()) . '_id';

            if ($config == $this->ownership) {
                $sql = "UPDATE {$table} SET assigned_user_id = '{$entity->get('assignedUserId')}' WHERE {$relatedField} = '{$entity->id}' AND is_inherit_assigned_user = 1 AND deleted = 0;";
                $this->getEntityManager()->nativeQuery($sql);
            }
        }

        if ($entity->isAttributeChanged('ownerUserId')) {
            $config = $this->getConfig()->get($this->ownerUserOwnership, '');
            $table = Util::toUnderScore($this->ownershipRelation);
            $relatedField = Util::toUnderScore($entity->getEntityType()) . '_id';

            if ($config == $this->ownership) {
                $sql = "UPDATE {$table} SET owner_user_id = '{$entity->get('ownerUserId')}' WHERE {$relatedField} = '{$entity->id}' AND is_inherit_owner_user = 1 AND deleted = 0;";
                $this->getEntityManager()->nativeQuery($sql);
            }
        }

        if ($entity->isAttributeChanged('teamsIds')) {
            $teamsOwnership = $this->getConfig()->get($this->teamsOwnership, '');

            if ($teamsOwnership == $this->ownership) {
                // get related entities ids that must inherit teams
                $table = Util::toUnderScore($this->ownershipRelation);
                $ids = $this
                    ->getEntityManager()
                    ->nativeQuery("SELECT id FROM {$table} WHERE is_inherit_teams = 1 AND deleted = 0;")
                    ->fetchAll(\PDO::FETCH_COLUMN);

                if (!empty($ids)) {
                    // delete old entity teams
                    $sql = "DELETE entity_team
                            FROM entity_team
                                INNER JOIN {$table} 
                                ON {$table}.id = entity_team.entity_id 
                                    AND entity_team.entity_type = '{$this->ownershipRelation}' 
                                    AND {$table}.deleted = 0
                            WHERE {$table}.is_inherit_teams = 1;";
                    $this
                        ->getEntityManager()
                        ->nativeQuery($sql);

                    // insert new teams to entities
                    $teamsIds = $entity->get('teamsIds');
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
    }

    /**
     * @param Entity $entity
     * @param string $target
     * @param string|null $config
     *
     */
    protected function inheritOwnership(Entity $entity, string $target, ?string $config)
    {
        if (!empty($config)) {
            $inheritedEntity = $this->getInheritedEntity($entity, $config);

            if ($inheritedEntity) {
                if ($target == 'teams') {
                    $teams = $inheritedEntity->get('teams')->toArray();

                    $entity->set('teamsIds', array_column($teams, 'id'));
                    $entity->set('teamsNames', array_column($teams, 'name', 'id'));
                } else {
                    $entity->set($target . 'Id', $inheritedEntity->get($target . 'Id'));
                    $entity->set($target . 'Name', $inheritedEntity->get($target .  'Name'));
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
