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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class FileTypeEntity extends AbstractEntityListener
{
    public function beforeRemove(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        $attr = $this->getEntityManager()->getRepository('Attribute')
            ->where(['fileTypeId' => $entity->get('id')])
            ->findOne();

        if (!empty($attr)) {
            throw new BadRequest(
                sprintf(
                    $this->getLanguage()->translate('fileTypeCannotBeDeleted', 'exceptions', 'Attribute'),
                    $attr->get('name')
                )
            );
        }
    }
}
