<?php
declare(strict_types=1);

namespace Pim\Services;

/**
 * Class GeneralStatisticsDashlet
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class GeneralStatisticsDashlet extends AbstractProductDashletService
{

    /**
     * Get general statistic
     *
     * @return array
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];

        $result['list'] = [
            [
                'id'     => 'product',
                'name'   => 'product',
                'amount' => (int)$this->getRepository('Product')->select(['id'])->where(['type' => $this->getProductTypes()])->count()
            ],
            [
                'id'     => 'category',
                'name'   => 'category',
                'amount' => (int)$this->getRepository('Category')->select(['id'])->count()
            ],
            [
                'id'     => 'productFamily',
                'name'   => 'productFamily',
                'amount' => (int)$this->getRepository('ProductFamily')->select(['id'])->count()
            ],
            [
                'id'     => 'attribute',
                'name'   => 'attribute',
                'amount' => (int)$this->getRepository('Attribute')->select(['id'])->count()
            ],
            [
                'id'     => 'productWithoutAssociatedProduct',
                'name'   => 'productWithoutAssociatedProduct',
                'amount' => $this->getAmountProductWithoutAssociatedProduct()
            ],
            [
                'id'     => 'productWithoutCategory',
                'name'   => 'productWithoutCategory',
                'amount' => $this->getAmountProductWithoutCategory()
            ]
        ];

        $this->addProductWithoutImage( $result['list']);

        $result['total'] = count($result['list']);

        return $result;
    }

    /**
     * Get query for Product without Image
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutImage($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql
            = "SELECT " . $select . " 
                FROM product as p 
                WHERE p.image_id IS NULL 
                  AND p.deleted = 0 
                  AND p.type IN " . $this->getProductTypesCondition();

        return $sql;
    }

    /**
     * Get query for Product without AssociatedProduct
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutAssociatedProduct($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql = "SELECT " . $select . " 
                FROM product as p 
                WHERE 
                    (SELECT COUNT(ap.id)                  
                    FROM associated_product AS ap
                      JOIN product AS p_rel 
                        ON p_rel.id = ap.related_product_id AND p_rel.deleted = 0
                      JOIN product AS p_main 
                        ON p_main.id = ap.related_product_id AND p_main.deleted = 0
                      JOIN association 
                        ON association.id = ap.association_id AND association.deleted = 0
                    WHERE ap.deleted = 0 AND  ap.main_product_id = p.id) = 0 
                AND p.deleted = 0 AND p.type IN " . $this->getProductTypesCondition();

        return $sql;
    }

    /**
     * Get query for Product without Category
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutCategory($count = false): string
    {
        // prepare types
        $types = $this->getProductTypesCondition();

        // prepare select
        $select = $count ? 'COUNT(p.id)' : 'p.id as id';

        return "SELECT $select 
                FROM product p 
                LEFT JOIN product_category pc ON pc.product_id=p.id AND pc.deleted=0
                WHERE p.deleted=0 
                  AND p.type IN $types
                  AND pc.id IS NULL";
    }

    /**
     * Get Amount Product without image
     *
     * @return int
     */
    protected function getAmountProductWithoutImage(): int
    {
        $sth = $this->getPDO()->prepare($this->getQueryProductWithoutImage(true));
        $sth->execute();

        return (int)$sth->fetchColumn();
    }

    /**
     * Get Amount Product without AssociatedProduct
     *
     * @return int
     */
    protected function getAmountProductWithoutAssociatedProduct(): int
    {
        $sth = $this->getPDO()->prepare($this->getQueryProductWithoutAssociatedProduct(true));
        $sth->execute();

        return (int)$sth->fetchColumn();
    }

    /**
     * Get amount Product without category
     *
     * @return int
     */
    protected function getAmountProductWithoutCategory(): int
    {
        $sth = $this->getPDO()->prepare($this->getQueryProductWithoutCategory(true));
        $sth->execute();

        return (int)$sth->fetchColumn();
    }

    /**
     * @param $list
     */
    protected function addProductWithoutImage(&$list)
    {
        if (!empty( $this->getInjection('metadata')->get('entityDefs.Product.fields.image'))) {
            $list[] =  [
                'id'     => 'productWithoutImage',
                'name'   => 'productWithoutImage',
                'amount' => $this->getAmountProductWithoutImage()
            ];
        }
    }

}
