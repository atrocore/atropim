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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Atro\Listeners\AbstractListener;

/**
 * Class BrandController
 */
class BrandController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeActionDelete(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if (empty($data['data']->force) && !empty($data['params']['id'])) {
            $this->validRelationsWithProduct([$data['params']['id']]);
        }
    }

    /**
     * @param Event $event
     */
    public function beforeActionMassDelete(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        if (empty($data->force) && !empty($data->ids)) {
            $this->validRelationsWithProduct($data->ids);
        }
    }


    /**
     * @param array $idsBrand
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProduct(array $idsBrand): void
    {
        if ($this->hasProducts($idsBrand)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'brandIsUsedInProducts',
                    'exceptions',
                    'Brand'
                )
            );
        }
    }

    /**
     * Is brand used in Products
     *
     * @param array $idsBrand
     *
     * @return bool
     */
    protected function hasProducts(array $idsBrand): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['brandId' => $idsBrand])
            ->count();

        return !empty($count);
    }
}
