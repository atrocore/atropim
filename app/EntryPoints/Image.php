<?php

declare(strict_types=1);

namespace Pim\EntryPoints;

use Espo\Core\Exceptions\NotFound;
use Treo\Entities\Attachment;
use Treo\EntryPoints\Image as Base;

/**
 * Class Image
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Image extends Base
{
    /**
     * @inheritDoc
     * @throws NotFound
     */
    protected function checkAttachment(Attachment $attachment): bool
    {
        if (in_array($attachment->get('relatedType'), ['Asset'])) {
            $entity = $this
                ->getEntityManager()
                ->getRepository('Asset')
                ->where(['fileId' => $attachment->get('id')])
                ->findOne();
            if (empty($entity)) {
                throw new NotFound();
            }
        } else {
            $entity = $attachment;
        }

        return $this->getAcl()->checkEntity($entity);
    }
}
