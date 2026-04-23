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
    path: '/Product/channelStatistics',
    methods: ['GET'],
    summary: 'Get channel statistics',
    description: 'Returns product counts per channel split by active and inactive.',
    tag: 'Product',
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
                                'description' => 'Total number of channel rows',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Channel rows',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'        => [
                                            'type'        => 'string',
                                            'description' => 'Channel ID',
                                        ],
                                        'name'      => [
                                            'type'        => 'string',
                                            'description' => 'Channel name',
                                        ],
                                        'products'  => [
                                            'type'        => 'integer',
                                            'description' => 'Total number of products assigned to this channel',
                                        ],
                                        'active'    => [
                                            'type'        => 'integer',
                                            'description' => 'Number of active products in this channel',
                                        ],
                                        'notActive' => [
                                            'type'        => 'integer',
                                            'description' => 'Number of inactive products in this channel',
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
class ProductChannelStatisticsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('ChannelsDashlet')->getDashlet());
    }
}
