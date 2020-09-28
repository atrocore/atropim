<?php

declare(strict_types=1);

namespace Pim;

use Espo\Core\Utils\Json;
use Treo\Core\ModuleManager\AbstractModule;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Util;

/**
 * Class Module
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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

        $result = $this->addImage($result);

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
                'type'      => 'isTrue',
                'attribute' => 'isRequired'
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
     * @return array
     */
    protected function addImage($data): array
    {
        if($this->container->get('metadata')->isModuleInstalled('Dam')) {
            $clientDefsAssociatedProduct = [
                "dynamicLogic" => [
                    "fields" => [
                        "mainProductImage" => [
                            "visible" => [
                                "conditionGroup" => [
                                    [
                                        "type" => "isNotEmpty",
                                        "attribute" => "id"
                                    ]
                                ]
                            ]
                        ],
                        "relatedProductImage" => [
                            "visible" => [
                                "conditionGroup" => [
                                    [
                                        "type" => "isNotEmpty",
                                        "attribute" => "id"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $clientDefsCategory = [
                "bottomPanels" => [
                    "detail" => [
                        [
                            "name" => "asset_relations",
                            "label" => "Asset Relations",
                            "view" => "dam:views/asset_relation/record/panels/bottom-panel",
                            "entityName" => "Category"
                        ]
                    ]
                ],
                "sidePanels" => [
                    "edit" => [
                        [
                            "name" => "image",
                            "label" => "Category Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ],
                    "detail" => [
                        [
                            "name" => "image",
                            "label" => "Category Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ],
                    "detailSmall" => [
                        [
                            "name" => "image",
                            "label" => "Category Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ]
                ]
            ];

            $clientDefsProduct = [
                "bottomPanels" => [
                    "detail" => [
                        [
                            "name" => "asset_relations",
                            "label" => "Asset Relations",
                            "view" => "pim:views/product/record/panels/asset-relation-bottom-panel",
                            "entityName" => "Product"
                        ]
                    ]
                ],
                "sidePanels" => [
                    "edit" => [
                        [
                            "name" => "image",
                            "label" => "Product Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ],
                    "detail" => [
                        [
                            "name" => "image",
                            "label" => "Product Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ],
                    "detailSmall" => [
                        [
                            "name" => "image",
                            "label" => "Product Preview",
                            "view" => "pim:views/product/fields/image"
                        ]
                    ]
                ],
                "menu" => [
                    "list" => [
                        "buttons" => [
                            [
                                "acl" => "read",
                                "label" => "",
                                "link" => "#Product/list",
                                "style" => "primary",
                                "title" => "List",
                                "iconHtml" => "<span class=\"fa fa-list\"></span>"
                            ],
                            [
                                "acl" => "read",
                                "label" => "",
                                "link" => "#Product/plate",
                                "style" => "default",
                                "title" => "Plate",
                                "iconHtml" => "<span class=\"fa fa-th\"></span>"
                            ]
                        ]
                    ],
                    "plate" => [
                        "buttons" => [
                            [
                                "acl" => "read",
                                "label" => "",
                                "link" => "#Product/list",
                                "style" => "default",
                                "title" => "List",
                                "iconHtml" => "<span class=\"fa fa-list\"></span>"
                            ],
                            [
                                "acl" => "read",
                                "label" => "",
                                "link" => "#Product/plate",
                                "style" => "primary",
                                "title" => "Plate",
                                "iconHtml" => "<span class=\"fa fa-th\"></span>"
                            ]
                        ]
                    ]
                ]
            ];

            $entityDefsAssociatedProduct = [
                "fields" => [
                    "mainProductImage" => [
                        "type" => "image",
                        "previewSize" => "small",
                        "readOnly" => true,
                        "notStorable" => true,
                        "view" => "pim:views/fields/full-width-list-image"
                    ],
                    "relatedProductImage" => [
                        "type" => "image",
                        "previewSize" => "small",
                        "readOnly" => true,
                        "notStorable" => true,
                        "view" => "pim:views/fields/full-width-list-image"
                    ]
                ]
            ];

            $entityDefsCategory = [
                "fields" => [
                    "image" => [
                        "type" => "image",
                        "previewSize" => "medium",
                        "readOnly" => true,
                        "view" => "pim:views/product/fields/image",
                        "importDisabled" => true
                    ],
                    "assets" => [
                        "type" => "linkMultiple",
                        "layoutDetailDisabled" => true,
                        "layoutMassUpdateDisabled" => true,
                        "importDisabled" => true,
                        "noLoad" => true
                    ]
                ],
                "links" => [
                    "image" => [
                        "type" => "belongsTo",
                        "entity" => "Attachment",
                        "skipOrmDefs" => true
                    ],
                    "assets" => [
                        "type" => "hasMany",
                        "relationName" => "categoryAsset",
                        "foreign" => "categories",
                        "entity" => "Asset",
                        "audited" => false
                    ]
                ]
            ];

            $entityDefsProduct = [
                "fields" => [
                    "image" => [
                        "type" => "image",
                        "previewSize" => "medium",
                        "readOnly" => true,
                        "view" => "pim:views/product/fields/image",
                        "importDisabled" => true
                    ],
                    "assets" => [
                        "type" => "linkMultiple",
                        "layoutDetailDisabled" => true,
                        "layoutMassUpdateDisabled" => true,
                        "importDisabled" => true,
                        "noLoad" => true
                    ]
                ],
                "links" => [
                    "assets" => [
                        "type" => "hasMany",
                        "relationName" => "productAsset",
                        "foreign" => "products",
                        "entity" => "Asset",
                        "audited" => false
                    ],
                    "image" => [
                        "type" => "belongsTo",
                        "entity" => "Attachment",
                        "skipOrmDefs" => true
                    ]
                ]
            ];

            $entityDefsAsset = [
                "fields" => [
                    "products" => [
                        "type" => "linkMultiple",
                        "layoutDetailDisabled" => true,
                        "layoutMassUpdateDisabled" => true,
                        "importDisabled" => true,
                        "noLoad" => true
                    ],
                    "categories" => [
                        "type" => "linkMultiple",
                        "layoutDetailDisabled" => true,
                        "layoutMassUpdateDisabled" => true,
                        "importDisabled" => true,
                        "noLoad" => true
                    ]
                ],
                "links" => [
                    "products" => [
                        "type" => "hasMany",
                        "relationName" => "productAsset",
                        "foreign" => "assets",
                        "entity" => "Product",
                        "audited" => false
                    ],
                    "categories" => [
                        "type" => "hasMany",
                        "relationName" => "categoryAsset",
                        "foreign" => "assets",
                        "entity" => "Category",
                        "audited" => false
                    ]
                ]
            ];

            $entityDefsAssetRelation = [
                "fields" => [
                    "scope" => [
                        "type" => "enum",
                        "required" => false,
                        "options" => [
                            "Global",
                            "Channel"
                        ],
                        "default" => "Global",
                        "isSorted" => false,
                        "audited" => false,
                        "readOnly" => false,
                        "tooltip" => false
                    ],
                    "channels" => [
                        "type" => "linkMultiple",
                        "importDisabled" => true,
                        "noLoad" => false,
                        "required" => false,
                        "readOnly" => false,
                        "tooltip" => false,
                        "view" => "pim:views/asset-relation/fields/channels"
                    ],
                    "role" => [
                        "type" => "multiEnum",
                        "storeArrayValues" => true,
                        "required" => false,
                        "fontSize" => 1,
                        "options" => [
                            "Main"
                        ],
                        "optionColors" => [
                            "Main" => "00BFFF"
                        ],
                        "audited" => false,
                        "readOnly" => false,
                        "tooltip" => false
                    ]
                ],
                "links" => [
                    "channels" => [
                        "type" => "hasMany",
                        "relationName" => "assetRelationChannel",
                        "foreign" => "assetRelations",
                        "entity" => "Channel"
                    ]
                ]
            ];

            $clientDefsAssetRelation = [
                "dynamicLogic" => [
                    "fields" => [
                        "scope" => [
                            "visible" => [
                                "conditionGroup" => [
                                    [
                                        "type" => "or",
                                        "value" => [
                                            [
                                                "type" => "equals",
                                                "attribute" => "entityName",
                                                "value" => "Product"
                                            ],
                                            [
                                                "type" => "equals",
                                                "attribute" => "entityName",
                                                "value" => "Category"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "channels" => [
                            "visible" => [
                                "conditionGroup" => [
                                    [
                                        "type" => "equals",
                                        "attribute" => "scope",
                                        "value" => "Channel"
                                    ]
                                ]
                            ],
                            "required" => [
                                "conditionGroup" => [
                                    [
                                        "type" => "equals",
                                        "attribute" => "scope",
                                        "value" => "Channel"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $entityDefsChannel = [
                "fields" => [
                    "assetRelations" => [
                        "type" => "linkMultiple",
                        "layoutListDisabled" => true,
                        "layoutListSmallDisabled" => true,
                        "layoutDetailDisabled" => true,
                        "layoutDetailSmallDisabled" => true,
                        "layoutMassUpdateDisabled" => true,
                        "importDisabled" => true,
                        "noLoad" => true
                    ]
                ],
                "links" => [
                    "assetRelations" => [
                        "type" => "hasMany",
                        "relationName" => "assetRelationChannel",
                        "foreign" => "channels",
                        "entity" => "AssetRelation"
                    ]
                ]
            ];

            $data['clientDefs']['AssociatedProduct'] =
                array_merge_recursive($data['clientDefs']['AssociatedProduct'], $clientDefsAssociatedProduct);

            $data['clientDefs']['Category'] = array_merge_recursive($data['clientDefs']['Category'], $clientDefsCategory);
            $data['clientDefs']['Product'] = array_merge_recursive($data['clientDefs']['Product'], $clientDefsProduct);

            $data['entityDefs']['AssociatedProduct'] =
                array_merge_recursive($data['entityDefs']['AssociatedProduct'], $entityDefsAssociatedProduct);

            $data['entityDefs']['Category'] = array_merge_recursive($data['entityDefs']['Category'], $entityDefsCategory);
            $data['entityDefs']['Product'] = array_merge_recursive($data['entityDefs']['Product'], $entityDefsProduct);
            $data['entityDefs']['Channel'] = array_merge_recursive($data['entityDefs']['Channel'], $entityDefsChannel);

            //create asset
            $data['entityDefs']['Asset'] = array_merge_recursive($data['entityDefs']['Asset'], $entityDefsAsset);
            $data['entityDefs']['AssetRelation'] = array_merge_recursive($data['entityDefs']['AssetRelation'], $entityDefsAssetRelation);
            $data['clientDefs']['AssetRelation'] = array_merge_recursive($data['clientDefs']['AssetRelation'], $clientDefsAssetRelation);

            //expansion GeneralStatistics
            $data['dashlets']['GeneralStatistics']['options']['defaults']['urlMap']['productWithoutImage'] =
                [
                    "url" => '#Product',
                    "options" => [
                        "boolFilterList" => [
                            "withoutImageAssets"
                        ]
                    ]
                ];
            $data['clientDefs']['Product']['boolFilterList'][] = 'withoutImageAssets';
            $data['clientDefs']['Product']['boolFilterList'][] = 'withoutImageAssets';
        }

        return $data;
    }
}
