<?php
declare(strict_types=1);

namespace Pim\Services;

/**
 * Class ProductsByTagDashlet
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductsByTagDashlet extends AbstractProductDashletService
{
    /**
     * Int Class
     */
    public function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

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
        // prepare data
        foreach ($tags as $tag) {
            $where = [
                'tag*' => "%\"$tag\"%",
                'type' => $this->getProductTypes()
            ];

            $result['list'][] = [
                'id'     => $tag,
                'name'   => $tag,
                'amount' => $this->getRepository('Product')->where($where)->count()
            ];
        }

        return $result;
    }
}
