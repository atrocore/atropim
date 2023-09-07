/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/record/detail-side', 'pim:views/record/detail-side',
    Dep => Dep.extend({
        ownershipOptions: {
            'assignedUser': {
                'config': 'assignedUserAttributeOwnership',
                'field': 'isInheritAssignedUser'
            },
            'ownerUser': {
                'config': 'ownerUserAttributeOwnership',
                'field': 'isInheritOwnerUser'
            },
            'teams': {
                'config': 'teamsAttributeOwnership',
                'field': 'isInheritTeams'
            }
        }
    })
);
