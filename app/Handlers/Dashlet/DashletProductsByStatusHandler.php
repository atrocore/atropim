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
    path: '/Dashlet/productsByStatus',
    methods: ['GET'],
    summary: 'Get products by status dashlet data',
    description: 'Returns product counts grouped by status.',
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
                                'description' => 'Total number of status rows',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Status rows',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'     => [
                                            'type'        => 'string',
                                            'description' => 'Status value',
                                        ],
                                        'name'   => [
                                            'type'        => 'string',
                                            'description' => 'Status label',
                                        ],
                                        'amount' => [
                                            'type'        => 'integer',
                                            'description' => 'Number of products with this status',
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
class DashletProductsByStatusHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('ProductsByStatusDashlet')->getDashlet());
    }
}
