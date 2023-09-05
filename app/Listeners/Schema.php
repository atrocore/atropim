<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;

class Schema extends AbstractEntityListener
{
    public function prepareQueries(Event $event): void
    {
        if (empty($event->getArgument('queries'))) {
            return;
        }

        $queries = [];
        foreach ($event->getArgument('queries') as $query) {
            if (strpos($query, 'IDX_TEXT_VALUE') !== false) {
                $query = str_replace("(text_value, deleted)", "(text_value(500), deleted)", $query);
            }
            $queries[] = $query;
        }

        $event->setArgument('queries', $queries);
    }
}
