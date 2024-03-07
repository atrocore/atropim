<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Acl;

use Espo\Core\Acl\Base;

class ProductCategory extends Base
{
    public function isRelationEntity(string $entityName): bool
    {
        if ($entityName === 'ProductCategory') {
            return false;
        }

        return parent::isRelationEntity($entityName);
    }
}
