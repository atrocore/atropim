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

declare(strict_types=1);

namespace Pim\Entities;

use Atro\Core\Templates\Entities\Relationship;
use Espo\ORM\IEntity;

class ClassificationAttribute extends Relationship
{
    protected $entityType = "ClassificationAttribute";

    public function getAttributeType($attribute)
    {
        if ($attribute === 'textValue' && $this->has('attributeId')) {
            if (!empty($attr = $this->get('attribute'))) {
                if (in_array($attr->get('type'), ['array', 'extensibleMultiEnum'])) {
                    return IEntity::JSON_ARRAY;
                }
            }
        }

        return parent::getAttributeType($attribute);
    }
}
