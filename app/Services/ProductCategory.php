<?php
declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;

/**
 * Class ProductCategory
 *
 * @author r.ratsun@gmail.com
 */
class ProductCategory extends Base
{
    /**
     * Remove ProductCategory by  categoryId and catalogId
     *
     * @param string $categoryId
     * @param string $catalogId
     */
    public function removeProductCategory(string $categoryId, string $catalogId): void
    {
        /** @var Category $serviceCategory */
        $serviceCategory = $this->getServiceFactory()->create('Category');
        // get id parent category and ids children category
        $categoriesIds = $serviceCategory->getIdsTree($categoryId);

        $productsCategory = $this
            ->getEntityManager()
            ->getRepository('ProductCategory')
            ->join('product')
            ->where(['categoryId' => $categoriesIds])
            ->where(['product.catalogId' => $catalogId])
            ->find()
            ->toArray();

        foreach ($productsCategory as $productCategory) {
            $this->deleteEntity($productCategory['id']);
        }
    }
}
