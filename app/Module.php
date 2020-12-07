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
use Treo\Core\Utils\Config;
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
            'wysiwyg'
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

        $this->setLocalesToChannels($data);

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
        $result['clientDefs']['Attribute']['dynamicLogic']['fields']['name']['required']['conditionGroup'] = [
            [
                'type'      => 'notIn',
                'attribute' => 'type',
                'value'     => [md5('some-str')]
            ]
        ];

        $result['clientDefs']['Attribute']['dynamicLogic']['fields']['typeValue']['visible']['conditionGroup'] = [
            [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => [
                    'enum',
                    'multiEnum',
                    'unit'
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
                'type'  => 'or',
                'value' => [
                    [
                        'type'      => 'isTrue',
                        'attribute' => 'isRequired'
                    ],
                    [
                        'type'      => 'isEmpty',
                        'attribute' => 'productFamilyAttributeId'
                    ]
                ]
            ]
        ];

        foreach ($this->getInputLanguageList() as $locale => $key) {
            /**
             * Attribute
             */
            $result['clientDefs']['Attribute']['dynamicLogic']['fields']['name' . $key]['required']['conditionGroup'] = [
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isMultilang'
                ]
            ];
            $result['clientDefs']['Attribute']['dynamicLogic']['fields']['name' . $key]['visible']['conditionGroup'] = [
                [
                    'type'      => 'in',
                    'attribute' => 'type',
                    'value'     => self::$multiLangTypes
                ],
                [
                    'type'      => 'isTrue',
                    'attribute' => 'isMultilang'
                ]
            ];
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
     * @param \stdClass $metadata
     */
    protected function setLocalesToChannels(\stdClass &$metadata)
    {
        // prepare result
        $data = Json::decode(Json::encode($metadata), true);

        /** @var Config $config */
        $config = $this->container->get('config');

        if ($config->get('isMultilangActive', false)) {
            $data['entityDefs']['Channel']['fields']['locales']['options'] = $config->get('inputLanguageList', []);
        }

        // set data
        $metadata = Json::decode(Json::encode($data));
    }

    /**
     * @param $data
     *
     * @return array
     */
    protected function addImage($data): array
    {
        $clientDefsAssociatedProduct = [
            "dynamicLogic" => [
                "fields" => [
                    "mainProductImage"    => [
                        "visible" => [
                            "conditionGroup" => [
                                [
                                    "type"      => "isNotEmpty",
                                    "attribute" => "id"
                                ]
                            ]
                        ]
                    ],
                    "relatedProductImage" => [
                        "visible" => [
                            "conditionGroup" => [
                                [
                                    "type"      => "isNotEmpty",
                                    "attribute" => "id"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $clientDefsCategory = [
            "sidePanels"         => [
                "edit"        => [
                    [
                        "name"  => "image",
                        "label" => "Category Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ],
                "detail"      => [
                    [
                        "name"  => "image",
                        "label" => "Category Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ],
                "detailSmall" => [
                    [
                        "name"  => "image",
                        "label" => "Category Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ]
            ],
            "relationshipPanels" => [
                "assets" => [
                    "layoutName" => "listSmallForCategory"
                ]
            ]
        ];

        $clientDefsProduct = [
            "sidePanels"         => [
                "edit"        => [
                    [
                        "name"  => "image",
                        "label" => "Product Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ],
                "detail"      => [
                    [
                        "name"  => "image",
                        "label" => "Product Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ],
                "detailSmall" => [
                    [
                        "name"  => "image",
                        "label" => "Product Preview",
                        "view"  => "pim:views/product/fields/image"
                    ]
                ]
            ],
            "relationshipPanels" => [
                "assets" => [
                    "layoutName" => "listSmallForProduct"
                ]
            ],
            "menu"               => [
                "list"  => [
                    "buttons" => [
                        [
                            "acl"      => "read",
                            "label"    => "",
                            "link"     => "#Product/list",
                            "style"    => "primary",
                            "title"    => "List",
                            "iconHtml" => "<span class=\"fa fa-list\"></span>"
                        ],
                        [
                            "acl"      => "read",
                            "label"    => "",
                            "link"     => "#Product/plate",
                            "style"    => "default",
                            "title"    => "Plate",
                            "iconHtml" => "<span class=\"fa fa-th\"></span>"
                        ]
                    ]
                ],
                "plate" => [
                    "buttons" => [
                        [
                            "acl"      => "read",
                            "label"    => "",
                            "link"     => "#Product/list",
                            "style"    => "default",
                            "title"    => "List",
                            "iconHtml" => "<span class=\"fa fa-list\"></span>"
                        ],
                        [
                            "acl"      => "read",
                            "label"    => "",
                            "link"     => "#Product/plate",
                            "style"    => "primary",
                            "title"    => "Plate",
                            "iconHtml" => "<span class=\"fa fa-th\"></span>"
                        ]
                    ]
                ]
            ]
        ];

        $entityDefsAssociatedProduct = [
            "fields" => [
                "mainProductImage"    => [
                    "type"        => "image",
                    "previewSize" => "small",
                    "readOnly"    => true,
                    "notStorable" => true,
                    "view"        => "pim:views/fields/full-width-list-image"
                ],
                "relatedProductImage" => [
                    "type"        => "image",
                    "previewSize" => "small",
                    "readOnly"    => true,
                    "notStorable" => true,
                    "view"        => "pim:views/fields/full-width-list-image"
                ]
            ]
        ];

        $clientDefsAsset = [
            "dynamicLogic" => [
                "fields" => [
                    "scope"   => [
                        "visible" => [
                            "conditionGroup" => [
                                [
                                    "type"      => "isNotEmpty",
                                    "attribute" => "scope"
                                ]
                            ]
                        ]
                    ],
                    "channel" => [
                        "visible"  => [
                            "conditionGroup" => [
                                [
                                    "type"      => "equals",
                                    "attribute" => "scope",
                                    'value'     => 'Channel'
                                ]
                            ]
                        ],
                        "required" => [
                            "conditionGroup" => [
                                [
                                    "type"      => "equals",
                                    "attribute" => "scope",
                                    'value'     => 'Channel'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ];

        $entityDefsCategory = [
            "fields" => [
                "image"  => [
                    "type"           => "image",
                    "previewSize"    => "medium",
                    "readOnly"       => true,
                    "view"           => "pim:views/product/fields/image",
                    "importDisabled" => true
                ],
                "assets" => [
                    "type"                     => "linkMultiple",
                    "layoutDetailDisabled"     => true,
                    "layoutMassUpdateDisabled" => true,
                    "importDisabled"           => true,
                    "noLoad"                   => true
                ]
            ],
            "links"  => [
                "image"  => [
                    "type"        => "belongsTo",
                    "entity"      => "Attachment",
                    "skipOrmDefs" => true
                ],
                "assets" => [
                    "type"         => "hasMany",
                    "relationName" => "categoryAsset",
                    "foreign"      => "categories",
                    "entity"       => "Asset",
                    "audited"      => false
                ]
            ]
        ];

        $entityDefsProduct = [
            "fields" => [
                "image"  => [
                    "type"           => "image",
                    "previewSize"    => "medium",
                    "readOnly"       => true,
                    "view"           => "pim:views/product/fields/image",
                    "importDisabled" => true
                ],
                "assets" => [
                    "type"                     => "linkMultiple",
                    "layoutDetailDisabled"     => true,
                    "layoutMassUpdateDisabled" => true,
                    "importDisabled"           => true,
                    "noLoad"                   => true,
                    'columns'                  => [
                        'assetChannel' => 'channel'
                    ]
                ]
            ],
            "links"  => [
                "assets" => [
                    "type"              => "hasMany",
                    "relationName"      => "productAsset",
                    "foreign"           => "products",
                    "entity"            => "Asset",
                    "audited"           => false,
                    "additionalColumns" => [
                        'channel' => [
                            'type' => 'varchar'
                        ]
                    ],
                ],
                "image"  => [
                    "type"        => "belongsTo",
                    "entity"      => "Attachment",
                    "skipOrmDefs" => true
                ]
            ]
        ];

        $entityDefsAsset = [
            "fields" => [
                "entityName" => [
                    "type"                      => "varchar",
                    "notStorable"               => true,
                    "layoutDetailDisabled"      => true,
                    "layoutDetailSmallDisabled" => true,
                    "layoutListDisabled"        => true,
                    "layoutListSmallDisabled"   => true,
                    "layoutMassUpdateDisabled"  => true,
                    "layoutFiltersDisabled"     => true,
                    "importDisabled"            => true,
                    "exportDisabled"            => true,
                ],
                "entityId"   => [
                    "type"                      => "varchar",
                    "notStorable"               => true,
                    "layoutDetailDisabled"      => true,
                    "layoutDetailSmallDisabled" => true,
                    "layoutListDisabled"        => true,
                    "layoutListSmallDisabled"   => true,
                    "layoutMassUpdateDisabled"  => true,
                    "layoutFiltersDisabled"     => true,
                    "importDisabled"            => true,
                    "exportDisabled"            => true,
                ],
                "scope"      => [
                    "type"                      => "enum",
                    "notStorable"               => true,
                    "prohibitedEmptyValue"      => true,
                    "options"                   => ["Global", "Channel"],
                    "default"                   => "Global",
                    "layoutDetailDisabled"      => true,
                    "layoutDetailSmallDisabled" => true,
                    "layoutListDisabled"        => true,
                    "layoutListSmallDisabled"   => true,
                    "layoutMassUpdateDisabled"  => true,
                    "layoutFiltersDisabled"     => true,
                    "importDisabled"            => true,
                    "exportDisabled"            => true,
                ],
                "channel"    => [
                    "type"                      => "varchar",
                    "notStorable"               => true,
                    "view"                      => "pim:views/asset/fields/channel",
                    "layoutDetailDisabled"      => true,
                    "layoutDetailSmallDisabled" => true,
                    "layoutListDisabled"        => true,
                    "layoutListSmallDisabled"   => true,
                    "layoutMassUpdateDisabled"  => true,
                    "layoutFiltersDisabled"     => true,
                    "importDisabled"            => true,
                    "exportDisabled"            => true,
                ],
                "channelId"  => [
                    "type"                      => "varchar",
                    "notStorable"               => true,
                    "layoutDetailDisabled"      => true,
                    "layoutDetailSmallDisabled" => true,
                    "layoutListDisabled"        => true,
                    "layoutListSmallDisabled"   => true,
                    "layoutMassUpdateDisabled"  => true,
                    "layoutFiltersDisabled"     => true,
                    "importDisabled"            => true,
                    "exportDisabled"            => true,
                ],
                "products"   => [
                    "type"                     => "linkMultiple",
                    "layoutDetailDisabled"     => true,
                    "layoutMassUpdateDisabled" => true,
                    "importDisabled"           => true,
                    "noLoad"                   => true
                ],
                "categories" => [
                    "type"                     => "linkMultiple",
                    "layoutDetailDisabled"     => true,
                    "layoutMassUpdateDisabled" => true,
                    "importDisabled"           => true,
                    "noLoad"                   => true
                ]
            ],
            "links"  => [
                "products"   => [
                    "type"         => "hasMany",
                    "relationName" => "productAsset",
                    "foreign"      => "assets",
                    "entity"       => "Product",
                    "audited"      => false
                ],
                "categories" => [
                    "type"         => "hasMany",
                    "relationName" => "categoryAsset",
                    "foreign"      => "assets",
                    "entity"       => "Category",
                    "audited"      => false
                ]
            ]
        ];

        $data['clientDefs']['AssociatedProduct'] = array_merge_recursive($data['clientDefs']['AssociatedProduct'], $clientDefsAssociatedProduct);
        $data['clientDefs']['Asset'] = array_merge_recursive($data['clientDefs']['Asset'], $clientDefsAsset);
        $data['clientDefs']['Category'] = array_merge_recursive($data['clientDefs']['Category'], $clientDefsCategory);
        $data['clientDefs']['Product'] = array_merge_recursive($data['clientDefs']['Product'], $clientDefsProduct);

        $data['entityDefs']['AssociatedProduct'] = array_merge_recursive($data['entityDefs']['AssociatedProduct'], $entityDefsAssociatedProduct);
        $data['entityDefs']['Asset'] = array_merge_recursive($data['entityDefs']['Asset'], $entityDefsAsset);
        $data['entityDefs']['Category'] = array_merge_recursive($data['entityDefs']['Category'], $entityDefsCategory);
        $data['entityDefs']['Product'] = array_merge_recursive($data['entityDefs']['Product'], $entityDefsProduct);

        //expansion GeneralStatistics
        $data['dashlets']['GeneralStatistics']['options']['defaults']['urlMap']['productWithoutImage'] = [
            "url"     => '#Product',
            "options" => [
                "boolFilterList" => [
                    "withoutImageAssets"
                ]
            ]
        ];
        $data['clientDefs']['Product']['boolFilterList'][] = 'withoutImageAssets';
        $data['clientDefs']['Product']['boolFilterList'][] = 'withoutImageAssets';

        return $data;
    }
}
