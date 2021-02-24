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

namespace Pim\Services;

use Pim\Repositories\AbstractRepository;
use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Util;
use Espo\Services\QueueManagerBase;

/**
 * Class QueueManagerOwnership
 */
class QueueManagerOwnership extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (isset($data['assignedUserProductOwnership'])) {
            $override = $data['overrideProductAssignedUser'] ?? false;
            $this->updateUserOwnership(
                'product', 'assigned_user', $data['assignedUserProductOwnership'], $override
            );
        }

        if (isset($data['ownerUserProductOwnership'])) {
            $override = $data['overrideProductOwnerUser'] ?? false;
            $this->updateUserOwnership(
                'product', 'owner_user', $data['ownerUserProductOwnership'], $override
            );
        }

        if (isset($data['teamsProductOwnership'])) {
            $override = $data['overrideProductTeams'] ?? false;
            $this->updateTeamsOwnership('product', $data['teamsProductOwnership'], $override);
        }

        if (isset($data['assignedUserAttributeOwnership'])) {
            $override = $data['overrideAttributeAssignedUser'] ?? false;
            $this->updateUserOwnership(
                'product_attribute_value', 'assigned_user', $data['assignedUserAttributeOwnership'], $override
            );
        }

        if (isset($data['ownerUserAttributeOwnership'])) {
            $override = $data['overrideAttributeOwnerUser'] ?? false;
            $this->updateUserOwnership(
                'product_attribute_value', 'owner_user', $data['ownerUserAttributeOwnership'], $override
            );
        }

        if (isset($data['teamsAttributeOwnership'])) {
            $override = $data['overrideAttributeTeams'] ?? false;
            $this->updateTeamsOwnership('product_attribute_value', $data['teamsAttributeOwnership'], $override);
        }

        return true;
    }

    /**
     * @param string $entity
     * @param string $field
     * @param string $config
     * @param bool $override
     * @param string|null $locale
     */
    protected function updateUserOwnership(string $entity, string $field, string $config, bool $override, string $locale = null)
    {
        if (!empty($inherited = $this->getInheritedEntity($config))) {
            $localeField = !empty($locale) ? $field . '_' . strtolower($locale) : $field;

            if ($override) {
                $this->getEntityManager()->nativeQuery("UPDATE {$entity} SET is_inherit_{$localeField} = 1 WHERE deleted = 0 AND is_inherit_{$localeField} = 0;");
            }

            $sql = "SELECT id, {$inherited}_id AS inherited, {$localeField}_id AS user FROM {$entity} WHERE is_inherit_{$localeField} = 1 AND deleted = 0;";

            $entities = $this->getEntityManager()->nativeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);

            if (count($entities) > 0) {
                $foreignField = $inherited == 'attribute' ? $localeField : $field;
                $sql = "SELECT id, {$foreignField}_id FROM {$inherited} WHERE deleted = 0;";
                $inherited = $this->getEntityManager()->nativeQuery($sql)->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_GROUP|\PDO::FETCH_COLUMN);

                $queries = 0;
                $sql = '';
                foreach ($entities as $item) {
                    if ($override) {
                        $sql .= "UPDATE {$entity} SET {$localeField}_id = '{$inherited[$item['inherited']]}' WHERE id = '{$item['id']}';";
                        $queries++;
                    } elseif ($item['user'] != $inherited[$item['inherited']]) {
                        $sql .= "UPDATE {$entity} SET is_inherit_{$localeField} = 0 WHERE id = '{$item['id']}';";
                        $queries++;
                    }

                    if ($queries == AbstractRepository::RECORDS_PER_QUERY) {
                        $this->getEntityManager()->nativeQuery($sql);
                        $queries = 0;
                        $sql = '';

                    }
                }

                if (!empty($sql)) {
                    $this->getEntityManager()->nativeQuery($sql);
                }
            }

            // for multilang owner and assigned users
            if (empty($locale) && $this->getConfig()->get('isMultilangActive', false)) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', Util::toCamelCase($entity, '_', true), 'fields', Util::toCamelCase($localeField)]);

                if ($fieldDefs['isMultilang'] ?? false) {
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                        $this->updateUserOwnership($entity, $field, $config, $override, $locale);
                    }
                }
            }
        }
    }

    /**
     * @param string $entity
     * @param string $config
     * @param bool $override
     * @param string|null $locale
     */
    protected function updateTeamsOwnership(string $entity, string $config, bool $override, string $locale = null)
    {
        if (!empty($inherited = $this->getInheritedEntity($config))) {
            $sql = '';
            $inheritedField = !empty($locale) ? 'is_inherit_teams_' . strtolower($locale) : 'is_inherit_teams';

            if ($override) {
                $sql .= "UPDATE {$entity} SET {$inheritedField} = 1 WHERE deleted = 0 AND {$inheritedField} = 0;";
                $sql .= "DELETE entity_team FROM entity_team
                        WHERE entity_team.entity_id IN (
                            SELECT {$this->prepareIdForQuery($entity, $locale)} FROM {$entity} WHERE {$entity}.{$inheritedField} = 1 AND {$entity}.deleted = 0
                        );
                ";

                $this->getEntityManager()->nativeQuery($sql);

                $entities = $this
                    ->getEntityManager()
                    ->nativeQuery("SELECT id, {$inherited}_id AS inherited FROM {$entity} WHERE {$inheritedField} = 1 AND deleted = 0;")
                    ->fetchAll(\PDO::FETCH_ASSOC);

                if (count($entities) > 0) {
                    $foreignField = $inherited == 'attribute' ? $this->prepareIdForQuery('inherited', $locale) : $this->prepareIdForQuery('inherited');
                    $sql = "SELECT inherited.id, et.team_id 
                        FROM {$inherited} AS inherited
                        INNER JOIN entity_team AS et
                            ON et.entity_id = {$foreignField} AND et.deleted = 0
                        WHERE inherited.deleted = 0;";

                    $inherited = $this
                        ->getEntityManager()
                        ->nativeQuery($sql)
                        ->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_COLUMN);

                    $queries = 0;
                    $sql = '';
                    $entityType = Util::toCamelCase($entity, '_', true);
                    foreach ($entities as $item) {
                        if (isset($inherited[$item['inherited']])) {
                            foreach ($inherited[$item['inherited']] as $teamId) {
                                $id = !empty($locale) ? $item['id'] . ProductAttributeValue::LOCALE_IN_ID_SEPARATOR . $locale : $item['id'];
                                $sql .= "INSERT INTO entity_team SET entity_id = '{$id}', entity_type = '{$entityType}', team_id = '{$teamId}';";
                                $queries++;

                                if ($queries == AbstractRepository::RECORDS_PER_QUERY) {
                                    $this->getEntityManager()->nativeQuery($sql);
                                    $queries = 0;
                                    $sql = '';
                                }
                            }
                        }
                    }

                    if (!empty($sql)) {
                        $this->getEntityManager()->nativeQuery($sql);
                    }
                }
            } else {
                // get entities where inherit teams switch on
                $entitiesIds = $this
                    ->getEntityManager()
                    ->nativeQuery("SELECT {$this->prepareIdForQuery($entity, $locale)}, {$inherited}_id FROM {$entity} WHERE {$inheritedField} = 1 AND deleted = 0")
                    ->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_COLUMN|\PDO::FETCH_UNIQUE);

                if (!empty($entitiesIds)) {
                    // get entities teams
                    $sql = "SELECT entity_id, team_id FROM entity_team
                        WHERE entity_team.deleted = 0 
                          AND entity_id IN ('" . implode("','", array_keys($entitiesIds)) . "')";
                    $entitiesTeams = $this
                        ->getEntityManager()
                        ->nativeQuery($sql)
                        ->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);

                    // get inherited entities teams
                    $condition = $inherited == 'attribute' ? $this->prepareIdForQuery($inherited, $locale) : $this->prepareIdForQuery($inherited);
                    $sql = "SELECT entity_id, team_id FROM entity_team
                        INNER JOIN {$inherited} ON entity_team.entity_id = {$condition} 
                        WHERE entity_team.deleted = 0 AND {$inherited}.deleted = 0";
                    $inheritedTeams = $this
                        ->getEntityManager()
                        ->nativeQuery($sql)
                        ->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);

                    $ids = [];
                    foreach ($entitiesIds as $entityId => $inheritedId) {
                        if (!empty($locale) && $inherited == 'attribute') {
                            $separator = ProductAttributeValue::LOCALE_IN_ID_SEPARATOR;
                            $inheritedId .= $separator . strtolower($locale);
                        }

                        $inheritTeams = $inheritedTeams[$inheritedId] ?? [];
                        $teamsIds = $entitiesTeams[$entityId] ?? [];

                        if (!empty(array_diff($teamsIds, $inheritTeams)) || !empty(array_diff($inheritTeams, $teamsIds))) {
                            $ids[] = !empty($locale) ? explode(ProductAttributeValue::LOCALE_IN_ID_SEPARATOR, $entityId)[0] : $entityId;
                        }
                    }

                    // set entities as not inherited there teams are not equals
                    if (!empty($ids)) {
                        $ids = implode("','", $ids);
                        $this
                            ->getEntityManager()
                            ->nativeQuery("UPDATE {$entity} SET {$inheritedField} = 0 WHERE id IN ('{$ids}')");
                    }
                }
            }

            // for multilang owner and assigned users
            if (empty($locale) && $this->getConfig()->get('isMultilangActive', false)) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', Util::toCamelCase($entity, '_', true), 'fields', 'isInheritTeams']);

                if ($fieldDefs['isMultilang'] ?? false) {
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {

                        $this->updateTeamsOwnership($entity, $config, $override, $locale);
                    }
                }
            }
        }
    }

    /**
     * @param string $alias
     * @param string|null $locale
     *
     * @return string
     */
    protected function prepareIdForQuery(string $alias, string $locale = null): string
    {
        $separator = ProductAttributeValue::LOCALE_IN_ID_SEPARATOR;

        return !empty($locale) ? "CONCAT({$alias}.id, '{$separator}', '{$locale}')" : $alias . '.id';
    }

    /**
     * @param string $config
     *
     * @return string|null
     */
    protected function getInheritedEntity(string $config): ?string
    {
        $result = null;

        switch ($config) {
            case 'fromCatalog':
                $result = 'catalog';
                break;
            case 'fromProductFamily':
                $result = 'product_family';
                break;
            case 'fromProduct':
                $result = 'product';
                break;
            case 'fromAttribute':
                $result = 'attribute';
                break;
        }

        return $result;
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
