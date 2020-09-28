<?php
declare(strict_types=1);

namespace Pim\Entities;

use Espo\Core\Templates\Entities\Base;

/**
 * Class ProductFamily
 *
 * @author r.ratsun@gmail.com
 */
class ProductFamily extends Base
{
    /**
     * @var string
     */
    protected $entityType = 'ProductFamily';

    /**
     * @return array
     */
    public function _getProductsIds(): array
    {
        $data = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->where(['productFamilyId' => $this->get('id')])
            ->find()
            ->toArray();

        return array_column($data, 'id');
    }
}
