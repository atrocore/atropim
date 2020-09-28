<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Treo\Core\Migration\AbstractMigration;
use Treo\Core\Utils\Util;

/**
 * Migration class for version 3.10.0
 *
 * @author r.ratsun@gmail.com
 */
class V3Dot10Dot0 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        // auth
        $this->auth();

        // delete old
        $this->execute('DELETE FROM pim_image WHERE 1;DELETE FROM pim_image_channel WHERE 1');

        // prepare repository
        $repository = $this->getEntityManager()->getRepository('PimImage');

        // get images
        $productImages = $this
            ->fetchAll(
                'SELECT 
                   pi.id          AS id,
                   pi.name        AS name, 
                   pi.image_id    AS imageId, 
                   pi.type        AS type, 
                   pi.image_link  AS link, 
                   pip.product_id AS productId,  
                   pip.scope      AS scope,
                   pip.sort_order AS sortOrder,
                    CASE
                        WHEN p.image_id = pi.image_id THEN 1
                        ELSE 0
                    END AS isMain
                FROM product_image_product AS pip 
                JOIN product_image AS pi ON pi.id=pip.product_image_id AND pi.deleted=0
                JOIN product AS p ON pip.product_id=p.id AND p.deleted=0  
                WHERE pip.deleted=0 
                ORDER BY pip.product_id ASC'
            );

        foreach ($productImages as $k => $productImage) {
            $sortOrder = $productImage['sortOrder'];
            if (is_null($sortOrder) && $productImage['isMain'] == '1') {
                //main image become first in list
                $sortOrder = 0 - $k;
            }
            $entity = $repository->get();
            $entity->set(
                [
                    'productId' => $productImage['productId'],
                    'name'      => $productImage['name'],
                    'type'      => $productImage['type'],
                    'link'      => $productImage['link'],
                    'scope'     => $productImage['scope'],
                    'imageId'   => $productImage['imageId'],
                    'sortOrder' => $sortOrder,
                ]
            );
            try {
                $this->getEntityManager()->saveEntity($entity);
            } catch (BadRequest $badRequest) {
                $this->setLog('Product', $productImage['productId'], $productImage['id'], $badRequest);
            } catch (Error $error) {
                $this->setLog('Product', $productImage['productId'], $productImage['id'], $error);
            }

            // get channels
            if ($productImage['scope'] == 'Channel') {
                $channels = $this
                    ->fetchAll(
                        'SELECT 
                            channel_id AS channelId
                         FROM product_image_channel
                         WHERE deleted=0
                         AND product_image_id=\'' . $productImage['id'] . '\'
                         AND product_id=\'' . $productImage['productId'] . '\''
                    );

                foreach ($channels as $row) {
                    // relate channel
                    $repository->relate($entity, 'channels', $row['channelId']);
                }
            }
        }

        // get category images
        $categoryImages = $this
            ->fetchAll(
                'SELECT 
                   ci.id           AS id,
                   ci.name         AS name, 
                   ci.image_id     AS imageId, 
                   ci.type         AS type, 
                   ci.image_link   AS link, 
                   cic.category_id AS categoryId,  
                   cic.scope       AS scope,
                   cic.sort_order  AS sortOrder,
                   CASE
                        WHEN c.image_id = ci.image_id THEN 1
                        ELSE 0
                    END AS isMain
                FROM category_image_category AS cic 
                JOIN category_image AS ci ON ci.id=cic.category_image_id AND ci.deleted=0
                JOIN category AS c ON cic.category_id=c.id AND c.deleted=0  
                WHERE cic.deleted=0 
                ORDER BY cic.category_id ASC'
            );

        foreach ($categoryImages as $k => $categoryImage) {
            $entity = $repository->get();

            $sortOrder = $categoryImage['sortOrder'];
            if (is_null($sortOrder) && $categoryImage['isMain'] == '1') {
                //main image become first in list
                $sortOrder = 0 - $k;
            }
            $entity->set(
                [
                    'categoryId' => $categoryImage['categoryId'],
                    'name'       => $categoryImage['name'],
                    'type'       => $categoryImage['type'],
                    'link'       => $categoryImage['link'],
                    'scope'      => $categoryImage['scope'],
                    'imageId'    => $categoryImage['imageId'],
                    'sortOrder'  => $sortOrder,
                ]
            );
            try {
                $this->getEntityManager()->saveEntity($entity);
            } catch (BadRequest $badRequest) {
                $this->setLog('Category', $categoryImage['categoryId'], $categoryImage['id'], $badRequest);
            } catch (Error $error) {
                $this->setLog('Category', $categoryImage['categoryId'], $categoryImage['id'], $error);
            }

            // get channels
            if ($categoryImage['scope'] == 'Channel') {
                $channels = $this
                    ->fetchAll(
                        'SELECT 
                            channel_id AS channelId
                         FROM category_image_channel
                         WHERE deleted=0
                         AND category_image_id=\'' . $categoryImage['id'] . '\'
                         AND category_id= \'' . $categoryImage['categoryId'] .'\''
                    );

                foreach ($channels as $row) {
                    // relate channel
                    $repository->relate($entity, 'channels', $row['channelId']);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
        // auth
        $this->auth();

        // delete old
        $this->execute('DELETE FROM product_image WHERE 1;DELETE FROM product_image_product WHERE 1;DELETE FROM product_image_channel WHERE 1');
        $this->execute('DELETE FROM category_image WHERE 1;DELETE FROM category_image_category WHERE 1;DELETE FROM category_image_channel WHERE 1');

        // get images
        $images = $this
            ->fetchAll(
                'SELECT 
                   pi.id          AS id,
                   pi.name        AS name, 
                   pi.image_id    AS imageId, 
                   pi.link        AS link, 
                   pi.category_id AS categoryId,  
                   pi.product_id  AS productId,
                   pi.scope       AS scope,
                   pi.sort_order  AS sortOrder
                FROM pim_image AS pi 
                JOIN attachment AS a ON a.id=pi.image_id AND a.deleted=0
                LEFT JOIN category AS c ON pi.category_id=c.id AND c.deleted=0  
                LEFT JOIN product AS p ON pi.product_id=p.id AND p.deleted=0
                WHERE pi.deleted=0'
            );

        foreach ($images as $image) {
            if (!empty($image['categoryId'])) {
                // create image
                $newImage = $this->getEntityManager()->getEntity('CategoryImage');
                $newImage->set(
                    [
                        'name'      => $image['name'] . '_' . Util::generateId(),
                        'alt'       => $image['name'],
                        'imageId'   => $image['imageId'],
                        'type'      => (!empty($image['link'])) ? 'Link' : 'File',
                        'imageLink' => $image['link']
                    ]
                );
                try {
                    $this->getEntityManager()->saveEntity($newImage);
                } catch (BadRequest $badRequest) {
                    $this->setLog('Category', $image['categoryId'], $image['imageId'], $badRequest);
                } catch (Error $error) {
                    $this->setLog('Category', $image['categoryId'], $image['imageId'], $error);
                }

                // relate category
                $this->execute(
                    "INSERT INTO category_image_category (category_id,category_image_id,sort_order,scope) VALUES ('" . $image['categoryId']
                    . "', '" . $newImage->get('id') . "', '" . $image['sortOrder'] . "', '" . $image['scope'] . "')"
                );

                // insert channels
                if ($image['scope'] == 'Channel') {
                    $channels = $this
                        ->fetchAll(
                            'SELECT channel_id AS channelId
                             FROM pim_image_channel
                             WHERE deleted=0 AND pim_image_id=\'' . $image['id'] . '\''
                        );

                    foreach ($channels as $row) {
                        $this->execute(
                            "INSERT INTO category_image_channel (id,category_id,category_image_id,channel_id) VALUES ('" . Util::generateId() . "', '" . $image['categoryId']
                            . "', '" . $newImage->get('id') . "', '" . $row['channelId'] . "')"
                        );
                    }
                }
            } elseif (!empty($image['productId'])) {
                // create image
                $newImage = $this->getEntityManager()->getEntity('ProductImage');
                $newImage->set(
                    [
                        'name'      => $image['name'] . '_' . Util::generateId(),
                        'alt'       => $image['name'],
                        'imageId'   => $image['imageId'],
                        'type'      => (!empty($image['link'])) ? 'Link' : 'File',
                        'imageLink' => $image['link']
                    ]
                );
                try {
                    $this->getEntityManager()->saveEntity($newImage);
                } catch (BadRequest $badRequest) {
                    $this->setLog('Product', $image['productId'], $image['imageId'], $badRequest);
                } catch (Error $error) {
                    $this->setLog('Product', $image['productId'], $image['imageId'], $error);
                }
                // relate category
                $this->execute(
                    "INSERT INTO product_image_product (product_id,product_image_id,sort_order,scope) VALUES ('" . $image['productId']
                    . "', '" . $newImage->get('id') . "', '" . $image['sortOrder'] . "', '" . $image['scope'] . "')"
                );

                // insert channels
                if ($image['scope'] == 'Channel') {
                    $channels = $this
                        ->fetchAll(
                            'SELECT channel_id AS channelId
                             FROM pim_image_channel
                             WHERE deleted=0 AND pim_image_id=\'' . $image['id'] . '\''
                        );

                    foreach ($channels as $row) {
                        $this->execute(
                            "INSERT INTO product_image_channel (id,product_id,product_image_id,channel_id) VALUES ('" . Util::generateId() . "', '" . $image['productId']
                            . "', '" . $newImage->get('id') . "', '" . $row['channelId'] . "')"
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    private function execute(string $sql)
    {
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare($sql);
        $sth->execute();

        return $sth;
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    private function fetchAll(string $sql): array
    {
        return $this
            ->execute($sql)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param $entityName
     * @param $imageId
     */
    private function setLog($entityName, $entityId, $imageId, \Exception $e) {
        $GLOBALS['log']->error('ErrorMigration in ' . $entityName . '-' . $entityId . 'ImageId:' . $imageId . '; Error: ' . $e->getMessage() . '.');
    }

    /**
     * Auth
     */
    private function auth(): void
    {
        $auth = new \Treo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();
    }
}
