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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Json;
use Atro\Listeners\AbstractListener;
use Espo\Core\Utils\Util;

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


        if(property_exists($data, 'allowSingleClassificationForProduct') && !empty($data->allowSingleClassificationForProduct)){
            $res = $this->getEntityManager()
                ->getConnection()
                ->createQueryBuilder()
                ->from('product', 'p')
                ->select('p.id, COUNT(pc.classification_id)')
                ->join('p','product_classification','pc','p.id=pc.product_id AND pc.deleted=:false')
                ->join('pc','classification','c', 'c.id=pc.classification_id AND pc.deleted=:false')
                ->where('p.deleted=:false AND c.deleted=false')
                ->groupBy('p.id')
                ->having('COUNT(pc.classification_id) > 1')
                ->setParameter('false',false, ParameterType::BOOLEAN)
                ->setMaxResults(30)
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

        $this->deleteMultiLangAttributeOnInputLanguageChange($data, 'ProductAttributeValue');
        $this->deleteMultiLangAttributeOnInputLanguageChange($data, 'ClassificationAttribute');
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

    protected function deleteMultiLangAttributeOnInputLanguageChange($data, $entityName){
        if(!isset($data->inputLanguageList)){
            return;
        }

        /** @var Connection $conn */
        $conn = $this->getContainer()->get('connection');
        $result = [true];
        $limit = 30000;
        $offset = 0;
        while(!empty($result)){
            $table = Util::toUnderScore($entityName);
            $result = $conn->createQueryBuilder()
                ->from($conn->quoteIdentifier($table))
                ->select('id')
                ->where('language NOT IN (:languages) AND language <> :main')
                ->andWhere('deleted=:false')
                ->setParameter('languages', $data->inputLanguageList, Mapper::getParameterType($data->inputLanguageList))
                ->setParameter('main', 'main', ParameterType::STRING)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            if(!empty($result)){
                $this->getService($entityName)->massRemove([
                    "ids" => array_column($result, 'id')
                ]);
            }

            if(count($result) < $limit){
                break;
            }
            $offset += $limit;
        }
    }
}