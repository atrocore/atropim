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

namespace Pim\Handlers\Dashlet;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Dashlet/productTypes',
    methods: ['GET'],
    summary: 'Get product types dashlet data',
    description: 'Returns product counts grouped by hierarchy type (non-hierarchical, hierarchies, lowest-level, bundles).',
    tag: 'Dashlet',
    responses: [
        200 => [
            'description' => 'Dashlet data',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type'        => 'integer',
                                'description' => 'Total number of product type rows',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Product type rows',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'        => [
                                            'type'        => 'string',
                                            'description' => 'Product type identifier',
                                        ],
                                        'name'      => [
                                            'type'        => 'string',
                                            'description' => 'Product type label',
                                        ],
                                        'total'     => [
                                            'type'        => 'integer',
                                            'description' => 'Total number of products of this type',
                                        ],
                                        'active'    => [
                                            'type'        => 'integer',
                                            'description' => 'Number of active products of this type',
                                        ],
                                        'notActive' => [
                                            'type'        => 'integer',
                                            'description' => 'Number of inactive products of this type',
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
class DashletProductTypesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('ProductTypesDashlet')->getDashlet());
    }
}
