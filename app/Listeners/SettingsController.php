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

namespace Pim\Listeners;

use Espo\Core\Utils\Json;
use Pim\Entities\Channel;
use Pim\Repositories\AbstractRepository;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class SettingsController
 */
class SettingsController extends AbstractListener
{
    protected $removeFields = [
        'overrideAttributeAssignedUser',
        'overrideAttributeOwnerUser',
        'overrideAttributeTeams',
        'overrideProductAssignedUser',
        'overrideProductOwnerUser',
        'overrideProductTeams'
    ];

    /**
     * @param Event $event
     */
    public function beforeActionUpdate(Event $event): void
    {
        // open session
        session_start();

        // set to session
        $_SESSION['isMultilangActive'] = $this->getConfig()->get('isMultilangActive', false);
        $_SESSION['inputLanguageList'] = $this->getConfig()->get('inputLanguageList', []);
    }

    /**
     * @param Event $event
     */
    public function afterActionUpdate(Event $event): void
    {
        $this->updateChannelsLocales();

        // cleanup
        unset($_SESSION['isMultilangActive']);
        unset($_SESSION['inputLanguageList']);

        $data = Json::decode(Json::encode($event->getArgument('data')), true);

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

        $this->removeConfigFields();
    }

    /**
     * Update Channel locales field
     */
    protected function updateChannelsLocales(): void
    {
        if (!$this->getConfig()->get('isMultilangActive', false)) {
            $this->getEntityManager()->nativeQuery("UPDATE channel SET locales=NULL WHERE 1");
        } elseif (!empty($_SESSION['isMultilangActive'])) {
            /** @var array $deletedLocales */
            $deletedLocales = array_diff($_SESSION['inputLanguageList'], $this->getConfig()->get('inputLanguageList', []));

            /** @var Channel[] $channels */
            $channels = $this
                ->getEntityManager()
                ->getRepository('Channel')
                ->select(['id', 'locales'])
                ->find();

            if (count($channels) > 0) {
                foreach ($channels as $channel) {
                    if (!empty($locales = $channel->get('locales'))) {
                        $newLocales = [];
                        foreach ($locales as $locale) {
                            if (!in_array($locale, $deletedLocales)) {
                                $newLocales[] = $locale;
                            }
                        }
                        $channel->set('locales', $newLocales);
                        $this->getEntityManager()->saveEntity($channel);
                    }
                }
            }
        }
    }

    /**
     * @param string $entity
     * @param string $field
     * @param string $config
     * @param bool $override
     */
    protected function updateUserOwnership(string $entity, string $field, string $config, bool $override)
    {
        if (!empty($inherited = $this->getInheritedEntity($config))) {
            $sql = '';

            if ($override) {
                $sql .= "UPDATE {$entity} SET is_inherit_{$field} = 1 WHERE deleted = 0 AND is_inherit_{$field} = 0;";
            }

            $sql .= "UPDATE {$entity} INNER JOIN {$inherited} ON {$entity}.{$inherited}_id = {$inherited}.id SET {$entity}.{$field}_id = {$inherited}.{$field}_id WHERE {$entity}.is_inherit_{$field} = 1;";

            $this->getEntityManager()->nativeQuery($sql);
        }
    }

    /**
     * @param string $entity
     * @param string $config
     * @param bool $override
     */
    protected function updateTeamsOwnership(string $entity, string $config, bool $override)
    {
        if (!empty($inherited = $this->getInheritedEntity($config))) {
            $sql = '';

            if ($override) {
                $sql .= "UPDATE {$entity} SET is_inherit_teams = 1 WHERE deleted = 0 AND is_inherit_teams = 0;";
            }

            $sql .= "DELETE entity_team FROM entity_team
                            INNER JOIN {$entity}
                                ON {$entity}.id = entity_team.entity_id
                                    AND {$entity}.deleted = 0
                            WHERE {$entity}.is_inherit_teams = 1;";

            $this->getEntityManager()->nativeQuery($sql);

            $entities = $this
                ->getEntityManager()
                ->nativeQuery(
                    "SELECT entity.id, entity_team.team_id FROM {$entity} AS entity
                            INNER JOIN {$inherited} AS inherited
                                ON entity.{$inherited}_id = inherited.id
                            INNER JOIN entity_team
                                ON entity_team.entity_id = inherited.id AND entity_team.deleted = 0
                        WHERE entity.is_inherit_teams = 1 AND entity.deleted = 0;"
                )
                ->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_GROUP);

            $count = 0;
            $sql = '';
            $entityType = Util::toCamelCase($entity, '_', true);
            foreach ($entities as $entityId => $teams) {
                foreach ($teams as $team) {
                    $sql .= "INSERT INTO entity_team SET entity_id = '{$entityId}', entity_type = '{$entityType}', team_id = '{$team['team_id']}';";
                    $count++;

                    if ($count == AbstractRepository::RECORDS_PER_QUERY ) {
                        $this->getEntityManager()->nativeQuery($sql);
                        $count = 0;
                        $sql = '';
                    }
                }
            }

            if (!empty($sql)) {
                $this->getEntityManager()->nativeQuery($sql);
            }
        }
    }

    /**
     * Remove unnecessary config fields
     */
    protected function removeConfigFields()
    {
        $config = $this->getConfig();

        foreach ($this->removeFields as $field) {
            if ($config->has($field)) {
                $config->remove($field);
            }
        }
        $config->save();
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
}