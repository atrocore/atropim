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

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Channel
 */
class Channel extends Base
{
    /**
     * @return array
     */
    public function getUsedLocales(): array
    {
        $locales = [];
        foreach ($this->select(['locales'])->find()->toArray() as $item) {
            if (!empty($item['locales'])) {
                $locales = array_merge($locales, $item['locales']);
            }
        }

        return array_values(array_unique($locales));
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this
            ->getEntityManager()
            ->getRepository('ProductChannel')
            ->where(['channelId' => $entity->get('id')])
            ->removeCollection();

        $this
            ->getEntityManager()
            ->getRepository('CategoryChannel')
            ->where(['channelId' => $entity->get('id')])
            ->removeCollection();

    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
