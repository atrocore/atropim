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

namespace Pim\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Listing extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('classificationId') || $entity->isAttributeChanged('channelId')) {
            $classification = $this->getEntityManager()
                ->getRepository('Classification')
                ->get($entity->get('classificationId'));

            if (empty($classification)) {
                throw new BadRequest("Classification with ID '{$entity->get('classificationId')}' does not exist.");
            }

            if ($classification->get('channelId') !== $entity->get('channelId')) {
                throw new BadRequest("Classification Channel and Listing Channel needs to be the same.");
            }

            if ($entity->isAttributeChanged('classificationId')) {
                if ($classification->get('entityId') !== 'Listing') {
                    throw new BadRequest("Wrong Classification has been chosen.");
                }
                $entity->set('classificationsIds', [$classification->get('id')]);
                $this->getMemoryStorage()->set('listingClassificationUpdated', true);
            }
        }
    }
}
