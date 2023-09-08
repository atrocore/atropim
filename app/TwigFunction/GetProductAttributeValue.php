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

namespace Pim\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class GetProductAttributeValue extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
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
            'channelId'   => $channelId
        ];

        return $this->getInjection('entityManager')->getRepository('ProductAttributeValue')->where($where)->findOne();
    }
}
