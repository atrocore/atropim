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

namespace Pim\Services;

/**
 * Class ProductsByTagDashlet
 */
class ProductsByTagDashlet extends AbstractDashletService
{
    /**
     * Get Product types
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];

        // get tags
        $tags = $this->getInjection('metadata')->get('entityDefs.Product.fields.tag.options');
        $tags = is_array($tags) ? $tags : [];

        $result['total'] = count($tags);
        foreach ($tags as $tag) {
            $result['list'][] = [
                'id'     => $tag,
                'name'   => $tag,
                'amount' => $this->getRepository('Product')->where(['tag*' => "%\"$tag\"%"])->count()
            ];
        }

        return $result;
    }
}
