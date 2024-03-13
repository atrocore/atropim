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

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Atro\Listeners\AbstractListener;

/**
 * Class SettingsController
 */
class SettingsController extends AbstractListener
{
    protected array $removeFields
        = [
            'assignedUserAttributeOwnership' => 'overrideAttributeAssignedUser',
            'ownerUserAttributeOwnership'    => 'overrideAttributeOwnerUser',
            'teamsAttributeOwnership'        => 'overrideAttributeTeams',
            'assignedUserProductOwnership'   => 'overrideProductAssignedUser',
            'ownerUserProductOwnership'      => 'overrideProductOwnerUser',
            'teamsProductOwnership'          => 'overrideProductTeams'
        ];

    public function beforeActionPatch(Event $event): void
    {
        $data = $event->getArgument('data');

        if (property_exists($data, 'productCanLinkedWithNonLeafCategories') && empty($data->productCanLinkedWithNonLeafCategories)) {
            $this->getEntityManager()->getRepository('Product')->unlinkProductsFromNonLeafCategories();
        }

        $channelsLocales = $this
            ->getEntityManager()
            ->getRepository('Channel')
            ->getUsedLocales();

        if (property_exists($data, 'isMultilangActive') && empty($data->isMultilangActive) && count($channelsLocales) > 1) {
            throw new BadRequest($this->getLanguage()->translate('languageUsedInChannel', 'exceptions', 'Settings'));
        }

        if (!empty($data->inputLanguageList)) {
            foreach ($channelsLocales as $locale) {
                if ($locale !== 'main' && !in_array($locale, $event->getArgument('data')->inputLanguageList)) {
                    throw new BadRequest($this->getLanguage()->translate('languageUsedInChannel', 'exceptions', 'Settings'));
                }
            }
        }
    }

    public function afterActionPatch(Event $event): void
    {
        $data = Json::decode(Json::encode($event->getArgument('data')), true);

        $qm = false;
        foreach (array_keys($this->removeFields) as $key) {
            if (isset($data[$key]) && $data[$key] != 'notInherit') {
                $qm = true;
                break;
            }
        }

        if ($qm) {
            $this
                ->getContainer()
                ->get('queueManager')
                ->push($this->getLanguage()->translate('updatingOwnershipInformation', 'queueManager', 'Settings'), 'QueueManagerOwnership', $data);
        }

        $this->removeConfigFields();
    }

    /**
     * Remove unnecessary config fields
     */
    protected function removeConfigFields()
    {
        $config = $this->getConfig();

        foreach (array_values($this->removeFields) as $field) {
            if ($config->has($field)) {
                $config->remove($field);
            }
        }
        $config->save();
    }

    protected function isExistsProductsLinkedWithNonLeafCategories(): bool
    {
        return $this->getEntityManager()->getRepository('Product')->isExistsProductsLinkedWithNonLeafCategories();
    }
}
