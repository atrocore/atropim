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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;
use Espo\Core\Exceptions\Error;

class V1Dot11Dot20 extends Base
{
    public function up(): void
    {
        $row = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('packaging')
            ->setMaxResults(1)
            ->fetchAssociative();

        if (!empty($row)) {
            $path = "custom/Espo/Custom/Resources/metadata/";
            file_put_contents($path . 'entityDefs/Packaging.json', $this->entityDefsData);
            file_put_contents($path . 'scopes/Packaging.json', $this->scopeData);
            file_put_contents($path . 'clientDefs/Packaging.json', $this->clientDefsData);

            $path = "custom/Espo/Custom/Resources/layouts/Packaging";
            if (!is_dir($path)) {
                mkdir($path);
            }
            foreach ($this->layoutData as $layout => $data) {
                $file = $path . "/$layout.json";
                if (!is_file($file)) {
                    file_put_contents($file, $data);
                }
            }

            $file = "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json";
            if (is_file($file)) {
                $custom = json_decode(file_get_contents($file), true);
            } else {
                $custom = ['fields' => [], 'links' => []];
            }
            $custom['fields']['packaging'] = [
                "type" => 'link'
            ];
            $custom['links']['packaging'] = [
                "type"    => "belongsTo",
                "entity"  => "Packaging",
                "foreign" => "products"
            ];

            file_put_contents($file, json_encode($custom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $fromSchema = $this->getCurrentSchema();
            $toSchema = clone $fromSchema;

            $toSchema->dropTable('packaging');

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->execute($sql);
            }
        }

        $this->updateComposer('atrocore/pim', '^1.11.20');
    }

    public function down(): void
    {
        throw new Error("Downgrade is prohibited");
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }


    protected string $scopeData = '{
  "entity": true,
  "layouts": true,
  "tab": true,
  "acl": true,
  "customizable": true,
  "importable": true,
  "notifications": true,
  "stream": false,
  "disabled": false,
  "type": "Base",
  "isCustom": true,
  "module": "Custom",
  "object": true,
  "hasOwner": true,
  "hasAssignedUser": true,
  "hasTeam": true,
  "hasActive": true
}';

    protected string $entityDefsData = '{
  "fields": {
    "name": {
      "type": "varchar",
      "required": true,
      "trim": true
    },
    "description": {
      "type": "text",
      "rows": 4,
      "lengthOfCut": 400,
       "isCustom": true
    },
    "price": {
      "type": "float",
       "isCustom": true
    },
    "createdAt": {
      "type": "datetime",
      "readOnly": true
    },
    "modifiedAt": {
      "type": "datetime",
      "readOnly": true
    },
    "createdBy": {
      "type": "link",
      "readOnly": true,
      "view": "views/fields/user"
    },
    "modifiedBy": {
      "type": "link",
      "readOnly": true,
      "view": "views/fields/user"
    },
    "products": {
      "type": "linkMultiple",
       "isCustom": true
    }
  },
  "links": {
    "createdBy": {
      "type": "belongsTo",
      "entity": "User"
    },
    "modifiedBy": {
      "type": "belongsTo",
      "entity": "User"
    },
    "products": {
      "type": "hasMany",
      "foreign": "packaging",
      "entity": "Product",
       "isCustom": true
    }
  },
  "collection": {
    "sortBy": "createdAt",
    "asc": false
  }
}
    ';

    protected string $clientDefsData = '{
    "controller": "controllers/record",
    "iconClass": "fas fa-box",
    "boolFilterList": [
        "onlyMy",
        "notUsedAssociations"
    ],
    "hiddenBoolFilterList": [
        "notUsedAssociations"
    ],
    "disabledMassActions": [
        "merge"
    ]
}';


    protected array $layoutData = [
        'detail'        => '[
    {
        "label": "Overview",
        "style": "default",
        "rows": [
            [
                {
                    "name": "isActive"
                },
                false
            ],
            [
                {
                    "name": "name"
                },
                {
                    "name": "price"
                }
            ],
            [
                {
                    "name": "description",
                    "fullWidth": true
                }
            ]
        ]
    }
]',
        'detailSmall'   => '[
    {
        "label": "",
        "style": "default",
        "rows": [
            [
                {
                    "name": "isActive"
                },
                false
            ],
            [
                {
                    "name": "name"
                },
                {
                    "name": "price"
                }
            ],
            [
                {
                    "name": "description",
                    "fullWidth": true
                }
            ]
        ]
    }
]',
        'list'          => '[
    {
        "name": "name",
        "link": true
    },
    {
        "name": "price"
    },
    {
        "name": "isActive"
    }
]',
        'listSmall'     => '[
    {
        "name": "name",
        "link": true
    },
    {
        "name": "price"
    },
    {
        "name": "isActive"
    }
]',
        'relationships' => '[
    {
        "name": "products"
    }
]'
    ];
}
