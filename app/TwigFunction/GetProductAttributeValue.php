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

declare(strict_types=1);

namespace Pim\TwigFunction;

use Espo\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class GetProductAttributeValue extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
    }

    /**
     * @param string $attributeId
     * @param string $channelId
     * @param string $language
     *
     * @return Entity|null
     */
    public function run(...$input)
    {
        if (empty($input[0])) {
            return null;
        }

        $currentPav = $this->getTemplateData('entity');
        if (empty($currentPav) || $currentPav->getEntityType() !== 'ProductAttributeValue') {
            return null;
        }

        $attributeId = $input[0];
        $channelId = empty($input[1]) ? '' : $input[1];
        $language = empty($input[2]) ? 'main' : $input[2];

        $where = [
            'attributeId' => (string)$attributeId,
            'productId'   => (string)$currentPav->get('productId'),
            'language'    => (string)$language,
            'scope'       => 'Global'
        ];

        if (!empty($channelId)) {
            $where['scope'] = 'Channel';
            $where['channelId'] = $channelId;
        }

        $pav = $this->getInjection('entityManager')->getRepository('ProductAttributeValue')->where($where)->findOne();
        if (!empty($pav)) {
            if ($pav->get('id') === $currentPav->get('id')) {
                return $currentPav;
            }
            $this->getInjection('serviceFactory')->create('ProductAttributeValue')->prepareEntityForOutput($pav);
            return $pav;
        }

        return null;
    }
}
