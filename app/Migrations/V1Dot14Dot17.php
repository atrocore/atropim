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

class V1Dot14Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-10 15:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE listing (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, product_id VARCHAR(36) DEFAULT NULL, channel_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_LISTING_CREATED_BY_ID ON listing (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_MODIFIED_BY_ID ON listing (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_PRODUCT_ID ON listing (product_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CHANNEL_ID ON listing (channel_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_ID ON listing (classification_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_OWNER_USER_ID ON listing (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ASSIGNED_USER_ID ON listing (assigned_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_NAME ON listing (name, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CREATED_AT ON listing (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_MODIFIED_AT ON listing (modified_at, deleted)");

            $langStr = '';
            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $langStr .= ", varchar_value_" . strtolower($code) . " VARCHAR(255) DEFAULT NULL, text_value_" . strtolower($code) . " TEXT DEFAULT NULL";
            }
            $this->exec("CREATE TABLE listing_attribute_value (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', bool_value BOOLEAN DEFAULT NULL, date_value DATE DEFAULT NULL, datetime_value TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, int_value INT DEFAULT NULL, int_value1 INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, float_value1 DOUBLE PRECISION DEFAULT NULL, varchar_value VARCHAR(255) DEFAULT NULL, text_value TEXT DEFAULT NULL $langStr, reference_value VARCHAR(255) DEFAULT NULL, json_value TEXT DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_LISTING_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON listing_attribute_value (deleted, listing_id, attribute_id)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_LISTING_ID ON listing_attribute_value (listing_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_ATTRIBUTE_ID ON listing_attribute_value (attribute_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_BOOL_VALUE ON listing_attribute_value (bool_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_DATE_VALUE ON listing_attribute_value (date_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_DATETIME_VALUE ON listing_attribute_value (datetime_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_INT_VALUE ON listing_attribute_value (int_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_INT_VALUE1 ON listing_attribute_value (int_value1, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_FLOAT_VALUE ON listing_attribute_value (float_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_FLOAT_VALUE1 ON listing_attribute_value (float_value1, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_VARCHAR_VALUE ON listing_attribute_value (varchar_value, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_TEXT_VALUE ON listing_attribute_value (text_value, deleted) WHERE (length(text_value) < 1000)");
            $this->exec("CREATE INDEX IDX_LISTING_ATTRIBUTE_VALUE_REFERENCE_VALUE ON listing_attribute_value (reference_value, deleted)");
            $this->exec("COMMENT ON COLUMN listing_attribute_value.json_value IS '(DC2Type:jsonObject)'");

            $this->exec("CREATE TABLE listing_file (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_LISTING_FILE_UNIQUE_RELATION ON listing_file (deleted, file_id, listing_id)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_CREATED_BY_ID ON listing_file (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_MODIFIED_BY_ID ON listing_file (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_FILE_ID ON listing_file (file_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_LISTING_ID ON listing_file (listing_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_CREATED_AT ON listing_file (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_FILE_MODIFIED_AT ON listing_file (modified_at, deleted)");

            $this->exec("CREATE TABLE user_followed_listing (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_LISTING_UNIQUE_RELATION ON user_followed_listing (deleted, user_id, listing_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_CREATED_BY_ID ON user_followed_listing (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_MODIFIED_BY_ID ON user_followed_listing (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_USER_ID ON user_followed_listing (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_LISTING_ID ON user_followed_listing (listing_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_CREATED_AT ON user_followed_listing (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_LISTING_MODIFIED_AT ON user_followed_listing (modified_at, deleted)");

            $this->exec("CREATE TABLE listing_classification (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_LISTING_CLASSIFICATION_UNIQUE_RELATION ON listing_classification (deleted, classification_id, listing_id)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_CREATED_BY_ID ON listing_classification (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_MODIFIED_BY_ID ON listing_classification (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_CLASSIFICATION_ID ON listing_classification (classification_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_LISTING_ID ON listing_classification (listing_id, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_CREATED_AT ON listing_classification (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_LISTING_CLASSIFICATION_MODIFIED_AT ON listing_classification (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE listing (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, product_id VARCHAR(36) DEFAULT NULL, channel_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, INDEX IDX_LISTING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_LISTING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_LISTING_PRODUCT_ID (product_id, deleted), INDEX IDX_LISTING_CHANNEL_ID (channel_id, deleted), INDEX IDX_LISTING_CLASSIFICATION_ID (classification_id, deleted), INDEX IDX_LISTING_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_LISTING_ASSIGNED_USER_ID (assigned_user_id, deleted), INDEX IDX_LISTING_NAME (name, deleted), INDEX IDX_LISTING_CREATED_AT (created_at, deleted), INDEX IDX_LISTING_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

            $langStr = '';
            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $langStr .= ", varchar_value_" . strtolower($code) . " VARCHAR(255) DEFAULT NULL, text_value_" . strtolower($code) . " LONGTEXT DEFAULT NULL";
            }
            $this->exec("CREATE TABLE listing_attribute_value (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', bool_value TINYINT(1) DEFAULT NULL, date_value DATE DEFAULT NULL, datetime_value DATETIME DEFAULT NULL, int_value INT DEFAULT NULL, int_value1 INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, float_value1 DOUBLE PRECISION DEFAULT NULL, varchar_value VARCHAR(255) DEFAULT NULL, text_value LONGTEXT DEFAULT NULL $langStr, reference_value VARCHAR(255) DEFAULT NULL, json_value LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', listing_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_LISTING_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP (deleted, listing_id, attribute_id), INDEX IDX_LISTING_ATTRIBUTE_VALUE_LISTING_ID (listing_id, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_ATTRIBUTE_ID (attribute_id, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_BOOL_VALUE (bool_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_DATE_VALUE (date_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_DATETIME_VALUE (datetime_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_INT_VALUE (int_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_INT_VALUE1 (int_value1, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_FLOAT_VALUE (float_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_FLOAT_VALUE1 (float_value1, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_VARCHAR_VALUE (varchar_value, deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_TEXT_VALUE (text_value(200), deleted), INDEX IDX_LISTING_ATTRIBUTE_VALUE_REFERENCE_VALUE (reference_value, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

            $this->exec("CREATE TABLE listing_file (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_LISTING_FILE_UNIQUE_RELATION (deleted, file_id, listing_id), INDEX IDX_LISTING_FILE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_LISTING_FILE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_LISTING_FILE_FILE_ID (file_id, deleted), INDEX IDX_LISTING_FILE_LISTING_ID (listing_id, deleted), INDEX IDX_LISTING_FILE_CREATED_AT (created_at, deleted), INDEX IDX_LISTING_FILE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_listing (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_LISTING_UNIQUE_RELATION (deleted, user_id, listing_id), INDEX IDX_USER_FOLLOWED_LISTING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_LISTING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_LISTING_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_LISTING_LISTING_ID (listing_id, deleted), INDEX IDX_USER_FOLLOWED_LISTING_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_LISTING_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE listing_classification (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, listing_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_LISTING_CLASSIFICATION_UNIQUE_RELATION (deleted, classification_id, listing_id), INDEX IDX_LISTING_CLASSIFICATION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_LISTING_CLASSIFICATION_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_LISTING_CLASSIFICATION_CLASSIFICATION_ID (classification_id, deleted), INDEX IDX_LISTING_CLASSIFICATION_LISTING_ID (listing_id, deleted), INDEX IDX_LISTING_CLASSIFICATION_CREATED_AT (created_at, deleted), INDEX IDX_LISTING_CLASSIFICATION_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
