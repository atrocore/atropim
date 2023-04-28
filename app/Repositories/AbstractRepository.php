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

use Espo\Core\Templates\Repositories\Hierarchy;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

/**
 * Class AbstractRepository
 */
abstract class AbstractRepository extends Hierarchy
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
            $inheritedField = 'is_inherit_teams';

            $ids = $this
                ->getEntityManager()
                ->nativeQuery(
                    "SELECT id 
                            FROM {$table}
                            WHERE {$relatedTable}_id = '{$entity->id}' AND {$inheritedField} = 1 AND deleted = 0;"
                )
                ->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($ids)) {
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
                    $entityId = $entity->id;
                    $inheritedId = $inheritedEntity->id;

                    if ($entity->getEntityType() == 'ProductAttributeValue') {
                        $locale = $entity->get('language');

                        if ($inheritedEntity->getEntityType() == 'Attribute' && $locale != 'main') {
                            $inheritedId .= '~' . strtolower($locale);
                        }
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
                    $inheritedField = $target;

                    if ($entity->getEntityType() == 'ProductAttributeValue') {
                        $locale = $entity->get('language');

                        if ($inheritedEntity->getEntityType() == 'Attribute' && $locale != 'main') {
                            $inheritedField .= Util::toCamelCase(strtolower($locale), '_', true);
                        }
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
