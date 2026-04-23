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

namespace Pim\Handlers\Product;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Product/productByTagStatistics',
    methods: ['GET'],
    summary: 'Get product by tag statistics',
    description: 'Returns product counts for each configured product tag.',
    tag: 'Product',
    responses: [
        200 => [
            'description' => 'Product by tag statistics',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type'        => 'integer',
                                'description' => 'Total number of tag rows',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Tag rows',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'     => [
                                            'type'        => 'string',
                                            'description' => 'Tag identifier',
                                        ],
                                        'name'   => [
                                            'type'        => 'string',
                                            'description' => 'Tag name',
                                        ],
                                        'amount' => [
                                            'type'        => 'integer',
                                            'description' => 'Number of products with this tag',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class ProductProductByTagStatisticsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('ProductsByTagDashlet')->getDashlet());
    }
}
