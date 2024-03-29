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

class V1Dot11Dot26 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP");
            $this->exec("ALTER TABLE product_attribute_value DROP scope");
            $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value (deleted, product_id, attribute_id, language, channel_id)");

            $this->exec("DROP INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP");
            $this->exec("ALTER TABLE classification_attribute DROP scope");
            $this->exec("CREATE UNIQUE INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute (deleted, classification_id, attribute_id, language, channel_id)");

            $this->exec("ALTER TABLE attribute DROP default_scope");
        } else {
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value");
            $this->exec("ALTER TABLE product_attribute_value DROP scope");
            $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value (deleted, product_id, attribute_id, language, channel_id)");

            $this->exec("DROP INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute");
            $this->exec("ALTER TABLE classification_attribute DROP scope");
            $this->exec("CREATE UNIQUE INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute (deleted, classification_id, attribute_id, language, channel_id)");

            $this->exec("ALTER TABLE attribute DROP default_scope");
        }

        $this->updateComposer('atrocore/pim', '^1.11.26');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited');
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
