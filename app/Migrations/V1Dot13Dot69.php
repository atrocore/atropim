<?php
/*
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

class V1Dot13Dot69 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-28 10:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('content_item')) {
            $table = $toSchema->createTable('content_item');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['default' => false, 'notnull' => true]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('rich_text', 'text', ['notnull' => false]);
            $table->addColumn('type', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('image_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);

            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    $locale = strtolower($language);

                    $table->addColumn('name_' . $locale, 'string', ['length' => 255, 'notnull' => false]);
                    $table->addColumn('description_' . $locale, 'text', ['notnull' => false]);
                    $table->addColumn('rich_text_' . $locale, 'text', ['notnull' => false]);
                }
            }

            $table->addIndex(['created_by_id', 'deleted'], 'IDX_CONTENT_ITEM_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_CONTENT_ITEM_MODIFIED_BY_ID');
            $table->addIndex(['created_at', 'deleted'], 'IDX_CONTENT_ITEM_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_CONTENT_ITEM_MODIFIED_AT');
            $table->addIndex(['name', 'deleted'], 'IDX_CONTENT_ITEM_NAME');

            $table->setPrimaryKey(['id']);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->execute($sql);
            }
        }

        if (!$toSchema->hasTable('product_content_item')) {
            $table = $toSchema->createTable('product_content_item');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('product_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('content_item_id', 'string', ['length' => 36, 'notnull' => false]);

            $table->addUniqueIndex(['deleted', 'product_id', 'content_item_id'], 'IDX_PRODUCT_CONTENT_ITEM_UNIQUE_RELATION');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_MODIFIED_BY_ID');
            $table->addIndex(['product_id', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_PRODUCT_ID');
            $table->addIndex(['content_item_id', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_CONTENT_ITEM_ID');
            $table->addIndex(['created_at', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_PRODUCT_CONTENT_ITEM_MODIFIED_AT');

            $table->setPrimaryKey(['id']);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->execute($sql);
            }
        }

        $this->migrateContent();
        $this->migrateContentGroup();
    }

    protected function migrateContent(): void
    {
        foreach (['clientDefs', 'entityDefs', 'scopes'] as $folder) {
            $path = "data/metadata/{$folder}/Content.json";

            $data = [];
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
            }

            $baseData = isset($this->{'content' . ucfirst($folder)}) ? json_decode($this->{'content' . ucfirst($folder)}, true) : [];
            $data = array_merge_recursive($baseData, $data);

            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $path = "data/layouts/Content";
        if (!is_dir($path)) {
            mkdir($path);
        }
        foreach ($this->contentlayouts as $layout => $data) {
            $file = $path  . "/$layout.json";
            if (!file_exists($file)) {
                file_put_contents($file, $data);
            }
        }

        $productCustomPath = "data/metadata/entityDefs/Product.json";
        $data = [];
        if (file_exists($productCustomPath)) {
            $data = json_decode(file_get_contents($productCustomPath), true);
        }
        $data = array_merge_recursive(json_decode($this->productCustomEntityDefs, true), $data);
        file_put_contents($productCustomPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function migrateContentGroup(): void
    {
        foreach (['clientDefs', 'entityDefs', 'scopes'] as $folder) {
            $path = "data/metadata/{$folder}/ContentGroup.json";

            $data = [];
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true);
            }

            $baseData = isset($this->{'contentGroup' . ucfirst($folder)}) ? json_decode($this->{'contentGroup' . ucfirst($folder)}, true) : [];
            $data = array_merge_recursive($baseData, $data);

            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $path = "data/layouts/ContentGroup";
        if (!is_dir($path)) {
            mkdir($path);
        }
        foreach ($this->contentGroupLayouts as $layout => $data) {
            $file = $path  . "/$layout.json";
            if (!file_exists($file)) {
                file_put_contents($file, $data);
            }
        }
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

    protected string $contentClientDefs = '
    {
      "controller": "controllers/record",
      "boolFilterList": [
        "onlyMy",
        "notEntity"
      ],
      "hiddenBoolFilterList": [
        "notEntity"
      ],
      "iconClass": "far fa-window-maximize"
    }
    ';

    protected string $contentEntityDefs = '
    {
      "fields": {
        "tags": {
          "type": "multiEnum",
          "optionColors": {},
          "default": [],
          "isCustom": true
        },
        "name": {
          "type": "varchar",
          "maxLength": 128,
          "required": true,
          "default": "",
          "trim": true
        },
        "status": {
          "type": "enum",
          "options": ["draft", "written", "reviewed", "published"],
          "optionsIds": ["draft", "written", "reviewed", "published"],
          "isCustom": true
        },
        "type": {
          "type": "enum",
          "options": ["review", "news", "promotional"],
          "optionsIds": ["review", "news", "promotional"],
          "isCustom": true
        },
        "description": {
          "type": "text",
          "maxLength": 512,
          "default": "",
          "trim": true
        },
        "text": {
          "type": "wysiwyg",
          "trim": true,
          "isCustom": true
        },
        "metaTitle": {
          "type": "varchar",
          "maxLength": 60,
          "default": "",
          "trim": true,
          "isCustom": true
        },
        "metaDescription": {
          "type": "text",
          "maxLength": 158,
          "default": "",
          "rowsMax": 2,
          "trim": true,
          "isCustom": true
        },
        "products": {
          "type": "linkMultiple",
          "isCustom": true
        },
        "contentGroup": {
          "type": "link",
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
        "ownerUser": {
          "type": "link",
          "required": true,
          "view": "views/fields/owner-user"
        },
        "assignedUser": {
          "type": "link",
          "required": true,
          "view": "views/fields/assigned-user"
        },
        "teams": {
          "type": "linkMultiple",
          "view": "views/fields/teams"
        }
      },
      "links": {
        "products": {
          "type": "hasMany",
          "entity": "Product",
          "relationName": "productContents",
          "foreign": "contents"
        },
        "contentGroup": {
          "type": "belongsTo",
          "foreign": "contents",
          "entity": "ContentGroup"
        },
        "createdBy": {
          "type": "belongsTo",
          "entity": "User"
        },
        "modifiedBy": {
          "type": "belongsTo",
          "entity": "User"
        },
        "ownerUser": {
          "type": "belongsTo",
          "entity": "User"
        },
        "assignedUser": {
          "type": "belongsTo",
          "entity": "User"
        },
        "teams": {
          "type": "hasMany",
          "entity": "Team",
          "relationName": "EntityTeam",
          "layoutRelationshipsDisabled": true
        }
      }
    }
    ';

    protected string $contentScopes = '
    {
      "entity": true,
      "layouts": true,
      "tab": true,
      "acl": true,
      "customizable": true,
      "importable": true,
      "notifications": true,
      "disabled": false,
      "type": "Base",
      "object": true,
      "hasOwner": true,
      "hasAssignedUser": true,
      "hasTeam": true,
      "isCustom": true
    }
    ';

    protected array $contentlayouts = [
        'detail' => '[
  {
    "label": "Overview",
    "style": "default",
    "rows": [
      [
        {
          "name": "name"
        },
        {
          "name": "tags"
        }
      ],
      [
        {
          "name": "contentGroup"
        },
        false
      ],
      [
        {
          "name": "status"
        },
        {
          "name": "type"
        }
      ],
      [
        {
          "name": "description",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "text",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "metaTitle",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "metaDescription",
          "fullWidth": true
        }
      ]
    ]
  }
]
',
        'detailSmall' => '[
  {
    "label": "",
    "style": "default",
    "rows": [
      [
        {
          "name": "name"
        },
        {
          "name": "tags"
        }
      ],
      [
        {
          "name": "contentGroup"
        },
        false
      ],
      [
        {
          "name": "status"
        },
        {
          "name": "type"
        }
      ],
      [
        {
          "name": "description",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "text",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "metaTitle",
          "fullWidth": true
        }
      ],
      [
        {
          "name": "metaDescription",
          "fullWidth": true
        }
      ]
    ]
  }
]
',
        'list' => '[
  {
    "name": "name",
    "link": true
  },
  {
    "name": "tags"
  },
  {
    "name": "status"
  },
  {
    "name": "modifiedAt"
  }
]
',
        'listForContentsInContentGroup' => '[
  {
    "name": "name",
    "link": true
  },
  {
    "name": "tags",
    "notSortable": true
  },
  {
    "name": "status"
  },
  {
    "name": "modifiedAt"
  }
]
',
        'listForContentsInProduct' => '[
  {
    "name": "name",
    "link": true
  },
  {
    "name": "tags",
    "notSortable": true
  },
  {
    "name": "status"
  },
  {
    "name": "modifiedAt"
  }
]
',
        'listSmall' => '[
  {
    "name": "name",
    "link": true
  },
  {
    "name": "tags"
  },
  {
    "name": "status"
  },
  {
    "name": "modifiedAt"
  }
]
',
        'relationships' => '[
  {
    "name": "products"
  }
] 
'
    ];

    protected string $productCustomEntityDefs = '
    {
        "fields": {
            "contents": {
                "type": "linkMultiple"
            }
        },
        "links": {
            "contents": {
                "type": "hasMany",
                "entity": "Content",
                "relationName": "productContents",
                "foreign": "products"
            }
        }
    }
    ';

    protected string $contentGroupClientDefs = '{
  "controller": "controllers/record",
  "boolFilterList": [
    "onlyMy",
    "notEntity"
  ],
  "hiddenBoolFilterList": [
    "notEntity"
  ],
  "iconClass": "far fa-window-restore"
}';

    protected string $contentGroupEntityDefs = '{
  "fields": {
    "name": {
      "type": "varchar",
      "maxLength": 60,
      "required": true,
      "trim": true
    },
    "contents": {
      "type": "linkMultiple"
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
    "ownerUser": {
      "type": "link",
      "required": true,
      "view": "views/fields/owner-user"
    },
    "assignedUser": {
      "type": "link",
      "required": true,
      "view": "views/fields/assigned-user"
    },
    "teams": {
      "type": "linkMultiple",
      "view": "views/fields/teams"
    }
  },
  "links": {
    "contents": {
      "type": "hasMany",
      "foreign": "contentGroup",
      "entity": "Content"
    },
    "createdBy": {
      "type": "belongsTo",
      "entity": "User"
    },
    "modifiedBy": {
      "type": "belongsTo",
      "entity": "User"
    },
    "ownerUser": {
      "type": "belongsTo",
      "entity": "User"
    },
    "assignedUser": {
      "type": "belongsTo",
      "entity": "User"
    },
    "teams": {
      "type": "hasMany",
      "entity": "Team",
      "relationName": "EntityTeam",
      "layoutRelationshipsDisabled": true
    }
  }
}
';

    protected string $contentGroupScopes = '{
  "entity": true,
  "layouts": true,
  "tab": true,
  "acl": true,
  "customizable": true,
  "importable": true,
  "notifications": true,
  "disabled": false,
  "type": "Base",
  "object": true,
  "hasOwner": true,
  "hasAssignedUser": true,
  "hasTeam": true,
  "isCustom": true
}';

    protected array $contentGroupLayouts = [
        'detail' => '[
  {
    "label": "Overview",
    "style": "default",
    "rows": [
      [
        {
          "name": "name"
        },
        false
      ]
    ]
  }
]',
        'detailSmall' => '[
  {
    "label": "",
    "style": "default",
    "rows": [
      [
        {
          "name": "name"
        },
        false
      ]
    ]
  }
]',
        'list' => '[
  {
    "name": "name",
    "link": true
  }
]',
        'listSmall' => '[
  {
    "name": "name",
    "link": true
  }
]',
        'relationships' => '[
  {
    "name": "contents"
  }
]'
    ];
}
