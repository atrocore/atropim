/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/user-with-avatar', 'class-replace!pim:views/fields/user-with-avatar', function (Dep) {

    return Dep.extend({

        setDefaultOwnerUser: function () {
            if (this.model.name === 'Product'){
                return !this.getConfig().get('ownerUserProductOwnership');
            }

            if (this.model.name === 'ProductAttributeValue'){
                return !this.getConfig().get('ownerUserAttributeOwnership');
            }

            return true;
        },

        setDefaultAssignedUser: function () {
            if (this.model.name === 'Product'){
                return !this.getConfig().get('assignedUserProductOwnership');
            }

            if (this.model.name === 'ProductAttributeValue'){
                return !this.getConfig().get('assignedUserAttributeOwnership');
            }

            return true;
        }
    });
});
