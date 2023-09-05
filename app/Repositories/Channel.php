<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
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

    public function relateCategories(Entity $entity, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->relateChannels($category, $entity, null, $options);
    }

    public function unrelateCategories(Entity $entity, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->unrelateChannels($category, $entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this
            ->getEntityManager()
            ->getRepository('ProductChannel')
            ->where(['channelId' => $entity->get('id')])
            ->removeCollection();

        if (!empty($categories = $entity->get('categories')) && count($categories) > 0) {
            foreach ($categories as $category) {
                $this->unrelateCategories($entity, $category, []);
            }
        }
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

        $this->addDependency('queueManager');
        $this->addDependency('language');
    }
}
