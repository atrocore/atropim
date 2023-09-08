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
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;

class GetProductAttributeValue extends AbstractTwigFunction
{
    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function run(string $attributeId, string $channelId = '', string $language = 'main'): ?Entity
    {
        $currentPav = $this->getTemplateData('entity');
        if (empty($currentPav) || $currentPav->getEntityType() !== 'ProductAttributeValue') {
            return null;
        }

        $where = [
            'attributeId' => $attributeId,
            'productId'   => (string)$currentPav->get('productId'),
            'language'    => $language,
            'channelId'   => $channelId
        ];

        return $this->entityManager->getRepository('ProductAttributeValue')->where($where)->findOne();
    }
}
