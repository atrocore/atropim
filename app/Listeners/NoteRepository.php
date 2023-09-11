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

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;

class NoteRepository extends AbstractEntityListener
{
    public function modifyNotifyUserList(Event $event): void
    {
        $entity = $event->getArgument('entity');
        $noteRepository = $event->getArgument('repository');


        if (is_array($entity->get('data')) && isset($entity->get('data')['id'])) {
            $pav = $this->getEntityManager()->getEntity('ProductAttributeValue', $entity->get('data')['id']);
            if (!empty($pav)) {
                $newUserIds = [];
                if (!empty($pav->get('ownerUserId'))) {
                    $newUserIds[] = $pav->get('ownerUserId');
                }
                if (!empty($pav->get('assignedUserId'))) {
                    $newUserIds[] = $pav->get('assignedUserId');
                }
                $teamIdList = $pav->getLinkMultipleIdList('teams');
                if (is_array($teamIdList)) {
                    foreach ($teamIdList as $teamId) {
                        $team = $this->getEntityManager()->getEntity('Team', $teamId);
                        if (!$team) {
                            continue;
                        }
                        $targetUserList = $this->getEntityManager()->getRepository('Team')->findRelated(
                            $team, 'users', array(
                                'whereClause' => array(
                                    'isActive' => true
                                )
                            )
                        );
                        foreach ($targetUserList as $user) {
                            if ($user->id === $this->getUser()->id) {
                                continue;
                            }
                            if (in_array($user->id, $newUserIds)) {
                                continue;
                            }
                            $newUserIds[] = $user->id;
                        }
                    }
                }

                $event->setArgument('notifyUserIdList', array_merge($event->getArgument('notifyUserIdList'), $newUserIds));
            }
        }
    }
}
