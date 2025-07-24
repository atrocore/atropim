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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot22 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-23 19:00:00');
    }

    public function up(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('catalog')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();

            if (empty($res)) {
                $this->exec("Drop Table catalog;");
                if ($this->isPgSQL()) {
                    $this->exec("DROP INDEX idx_product_catalog_id;");
                } else {
                    $this->exec("DROP INDEX idx_product_catalog_id on product;");
                }
                $this->exec("Alter Table product drop column catalog_id;");
            } else {
                $fileName = "data/metadata/scopes/Catalog.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data = array_merge(json_decode(<<<'EOD'
{
  "isCustom": true,
  "entity": true,
  "layouts": true,
  "tab": true,
  "acl": true,
  "customizable": true,
  "importable": true,
  "notifications": true,
  "streamDisabled": true,
  "disabled": false,
  "type": "Base",
  "object": true,
  "hasAssignedUser": true,
  "hasTeam": false,
  "hasOwner": false,
  "hasActivities": false,
  "hasTasks": false,
  "hasActive": true
}
EOD, true), $data);
                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $fileName = "data/metadata/clientDefs/Catalog.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data = array_merge(json_decode(<<<'EOD'
{
  "controller": "controllers/record",
  "iconClass": "book"
}
EOD, true), $data);
                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


                $fileName = "data/metadata/entityDefs/Catalog.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $defaultJson = <<<'EOD'
{
  "fields": {
    "name": {
      "type": "varchar",
      "required": true,
      "trim": true,
      "isMultilang": true
    },
    "code": {
      "type": "varchar",
      "trim": true,
      "unique": true
    },
    "description": {
      "type": "text",
      "isMultilang": true,
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
      "readOnly": true,
      "noLoad": true,
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
      "foreign": "catalog",
      "entity": "Product"
    }
  },
  "collection": {
    "sortBy": "createdAt",
    "asc": false,
    "textFilterFields": [
      "name",
      "code"
    ]
  },
  "indexes": {
    "name": {
      "columns": [
        "name",
        "deleted"
      ]
    }
  }
}
EOD;

                file_put_contents($fileName, json_encode(Util::merge(json_decode($defaultJson, true), $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $fileName = "data/metadata/entityDefs/Product.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data['fields']['catalog'] = [
                    "type"     => "link",
                    "required" => false,
                    "isCustom" => true,
                ];

                $data['links']['catalog'] = [
                    "type"    => "belongsTo",
                    "foreign" => "products",
                    "entity"  => "Catalog"
                ];

                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Throwable $e) {

        }


        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('product_serie')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();

            if (empty($res)) {
                $this->exec("Drop Table product_serie;");
                if ($this->isPgSQL()) {
                    $this->exec("DROP INDEX idx_product_product_serie_id;");
                } else {
                    $this->exec("DROP INDEX idx_product_product_serie_id on product;");
                }
                $this->exec("Alter Table product drop column product_serie_id;");
            } else {
                $fileName = "data/metadata/scopes/ProductSerie.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data = array_merge(json_decode(<<<'EOD'
{
  "isCustom": true,
  "entity": true,
  "layouts": true,
  "tab": true,
  "acl": true,
  "customizable": true,
  "importable": true,
  "notifications": true,
  "disabled": false,
  "streamDisabled": true,
  "type": "Base",
  "object": true,
  "hasAssignedUser": true,
  "hasTeam": true,
  "hasOwner": true,
  "hasActivities": false,
  "hasTasks": false
}
EOD, true), $data);
                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $fileName = "data/metadata/clientDefs/ProductSerie.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data = array_merge(json_decode(<<<'EOD'
{
  "controller": "controllers/record",
  "iconClass": "trolley-suitcase"
}
EOD, true), $data);
                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


                $fileName = "data/metadata/entityDefs/ProductSerie.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $defaultJson = <<<'EOD'
{
  "fields": {
    "name": {
      "type": "varchar",
      "required": true,
      "trim": true
    },
    "description": {
      "type": "text",
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
      "noLoad": true,
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
    "assignedUser": {
      "type": "belongsTo",
      "entity": "User"
    },
    "products": {
      "type": "hasMany",
      "foreign": "productSerie",
      "entity": "Product"
    }
  },
  "collection": {
    "sortBy": "createdAt",
    "asc": false
  },
  "indexes": {
    "name": {
      "columns": [
        "name",
        "deleted"
      ]
    }
  }
}
EOD;

                file_put_contents($fileName, json_encode(Util::merge(json_decode($defaultJson, true), $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $fileName = "data/metadata/entityDefs/Product.json";
                $data = [];
                if (file_exists($fileName)) {
                    $data = json_decode(file_get_contents($fileName), true);
                }

                $data['fields']['productSerie'] = [
                    "type"     => "link",
                    "required" => false,
                    "isCustom" => true,
                ];

                $data['links']['productSerie'] = [
                    "type"    => "belongsTo",
                    "foreign" => "products",
                    "entity"  => "ProductSerie"
                ];

                file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Throwable $e) {

        }

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
