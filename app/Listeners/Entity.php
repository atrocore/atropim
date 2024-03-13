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

class Entity extends AbstractEntityListener
{
    public function beforeGetSelectParams(Event  $event) {
        $params = $event->getArgument('params');
        $entityType = $event->getArgument('entityType');
        if($entityType === 'ExtensibleEnumOption') {
            if (!empty($params['where']) ) {
                foreach ($params['where'] as $key => $filter) {
                    if(!empty($filter['type']) && $filter['type'] === 'bool'){
                        foreach ($filter['value'] as $boolFilter){
                            $method = "boolFilter".ucfirst($boolFilter);
                            if(method_exists($this, $method)){
                               $this->$method($params, isset($filter['data'][$boolFilter]) ? $filter['data'][$boolFilter] : null);
                            }
                        }
                    }
                }
            }
        }

        $event->setArgument("params", $params);
    }

    protected  function boolFilterOnlyForClassificationAttributesUsingPavId(&$params, ?string $pavId)
    {
        if(empty($pavId)){
            return $params;
        }
        $pavService = $this->getService('ProductAttributeValue');
        $classificationAttributeIds = $pavService->getClassificationAttributesFromPavId($pavId);

        if($this->getEntityManager()
            ->getRepository('ClassificationAttributeExtensibleEnumOption')
            ->where(['classificationAttributeId' => $classificationAttributeIds])
            ->count() > 0){

            $params['where'][] = [
                "type" => "linkedWith",
                "attribute" => "classificationAttributes",
                "value" =>  $classificationAttributeIds
            ];

        }

        return $params;
    }

    protected  function boolFilterOnlyExtensibleEnumIds(&$params, ?array $ids){

        if(empty($ids)){
            return $params;
        }

        $params['where'][] = [
            "type" => "in",
            "attribute" => "id",
            "value" =>  $ids
        ];
        return $params;
    }

}