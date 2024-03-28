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
use Atro\Core\Exceptions\BadRequest;
use Doctrine\DBAL\ParameterType;
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

        if(property_exists($data, 'allowSingleClassificationForProduct') && !empty($data->allowSingleClassificationForProduct)){
            $res = $this->getEntityManager()
                ->getConnection()
                ->createQueryBuilder()
                ->from('product', 'p')
                ->select('p.id, COUNT(pc.classification_id)')
                ->leftJoin('p','product_classification','pc','p.id=pc.product_id')
                ->where('p.deleted=:false AND pc.deleted=:false')
                ->groupBy('p.id')
                ->having('COUNT(pc.classification_id) > 1')
                ->setParameter('false',false, ParameterType::BOOLEAN)
                ->setMaxResults(100)
                ->fetchAllAssociative();

            if(count($res) > 0){
                $message = $this->getLanguage()->translate('someProductsHaveMoreThanOneClassification', 'exceptions', 'Product');
                if ($this->getConfig()->get('hasQueryBuilderFilter')) {
                    $rules = [];
                    foreach ($res as $item) {
                        $rules[] = ['id' => 'id', 'operator' => 'equal', 'value' => $item['id']];
                    }
                    $where = ['condition' => 'OR', 'rules' => $rules];
                    $url = $this->getConfig()->get('siteUrl') . '/?where=' . htmlspecialchars(json_encode($where), ENT_QUOTES, 'UTF-8') . '#' . 'Product';
                    $message .= ' <a href="' . $url . '" target="_blank">' . $this->getLanguage()->translate('See more') . '</a>.';
                }
                throw new BadRequest($message);
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
