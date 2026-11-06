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

namespace Pim\Dashlets;

use Atro\Core\Dashlets\DashletInterface;
use Atro\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

/**
 * Counts the products of each tag.
 */
class ProductsByTag implements DashletInterface
{
    public function __construct(
        private readonly Metadata $metadata,
        private readonly EntityManager $entityManager
    ) {
    }

    public function getData(array $params): array
    {
        $tags = $this->metadata->get('entityDefs.Product.fields.tag.options');
        $tags = is_array($tags) ? $tags : [];

        $list = [];
        foreach ($tags as $tag) {
            $list[] = [
                'id'     => $tag,
                'name'   => $tag,
                'amount' => $this->entityManager->getRepository('Product')->where(['tag*' => "%\"$tag\"%"])->count()
            ];
        }

        return ['total' => count($tags), 'list' => $list];
    }
}
