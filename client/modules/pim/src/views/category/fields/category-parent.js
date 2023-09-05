/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/category/fields/category-parent', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['notEntity', 'notChildren'],

        boolFilterData: {
            notEntity() {
                return this.model.id || this.model.get('ids') || [];
            },
            notChildren() {
                return this.model.get('id');
            }
        },

    })
);
