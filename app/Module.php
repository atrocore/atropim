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

namespace Pim;

use Espo\Core\Utils\Json;
use Treo\Core\ModuleManager\AbstractModule;
use Espo\Core\Utils\Config;
use Treo\Core\Utils\Util;

/**
 * Class Module
 */
class Module extends AbstractModule
{
    /**
     * @var array
     */
    public static $multiLangTypes
        = [
            'bool',
            'enum',
            'multiEnum',
            'varchar',
            'text',
            'wysiwyg',
            'asset'
        ];

    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5120;
    }

    /**
     * @inheritDoc
     */
    public function loadMetadata(\stdClass &$data)
    {
        parent::loadMetadata($data);

        // prepare result
        $result = Json::decode(Json::encode($data), true);

        // prepare attribute scope
        $result = $this->attributeScope($result);

        // add images
        if ($this->container->get('metadata')->isModuleInstalled('Dam')) {
            $result = $this->addImage($result);
        }

        // set data
        $data = Json::decode(Json::encode($result));
    }

    /**
     * @param array $result
     *
     * @return array
     */
    protected function attributeScope(array $result): array
    {
        /**
         * Attribute
         */
        $result['clientDefs']['Attribute']['dynamicLogic']['fields']['isMultilang']['visible']['conditionGroup'] = [
            [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => self::$multiLangTypes
            ]
        ];

        $result['clientDefs']['Attribute']['dynamicLogic']['fields']['typeValue']['visible']['conditionGroup'] = [
            [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => [
                    'enum',
                    'multiEnum'
                ]
            ]
        ];
        $result['clientDefs']['Attribute']['dynamicLogic']['fields']['typeValue']['required']['conditionGroup'] = [
            [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => [
                    'enum',
                    'multiEnum'
                ]
            ]
        ];

        /**
         * ProductAttributeValue
         */
        $result['clientDefs']['ProductAttributeValue']['dynamicLogic']['fields']['value']['required']['conditionGroup'] = [
            [
                'type'      => 'isTrue',
                'attribute' => 'isRequired'
            ]
        ];

        foreach ($this->getInputLanguageList() as $locale => $key) {
            /**
             * Attribute
             */
            $result['clientDefs']['Attribute']['dynamicLogic']['fields']['typeValue' . $key]['visible']['conditionGroup'] = [
                [
                    'type'      => 'in',
                    'attribute' => 'type',
                    'value'     => ['enum', 'multiEnum']
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isMultilang'
                ]
            ];
            $result['clientDefs']['Attribute']['dynamicLogic']['fields']['typeValue' . $key]['required']['conditionGroup'] = [
                [
                    'type'      => 'in',
                    'attribute' => 'type',
                    'value'     => ['enum', 'multiEnum']
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isMultilang'
                ]
            ];

            /**
             * ProductAttributeValue
             */
            $result['clientDefs']['ProductAttributeValue']['dynamicLogic']['fields']['value' . $key]['visible']['conditionGroup'] = [
                [
                    'type'      => 'isTrue',
                    'attribute' => 'attributeIsMultilang'
                ]
            ];
            $result['clientDefs']['ProductAttributeValue']['dynamicLogic']['fields']['value' . $key]['readOnly']['conditionGroup'] = [
                [
                    'type'      => 'in',
                    'attribute' => 'attributeType',
                    'value'     => ['enum', 'multiEnum']
                ]
            ];
            $result['clientDefs']['ProductAttributeValue']['dynamicLogic']['fields']['value' . $key]['required']['conditionGroup'] = [
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isRequired'
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'attributeIsMultilang'
                ]
            ];
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getInputLanguageList(): array
    {
        $result = [];

        /** @var Config $config */
        $config = $this->container->get('config');

        if ($config->get('isMultilangActive', false)) {
            foreach ($config->get('inputLanguageList', []) as $locale) {
                $result[$locale] = ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addImage(array $data): array
    {
        $data['dashlets'] = array_merge_recursive($data['dashlets'], $data['dashletsForDam']);
        $data['clientDefs'] = array_merge_recursive($data['clientDefs'], $data['clientDefsForDam']);
        $data['entityDefs'] = array_merge_recursive($data['entityDefs'], $data['entityDefsForDam']);

        return $data;
    }
}
