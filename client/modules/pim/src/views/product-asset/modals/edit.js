/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-asset/modals/edit', 'views/modals/edit',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', model => {
                if (this.getParentModel()) {
                    this.getParentModel().trigger('asset:saved');
                }
            });
        },

        getParentModel() {
            if (this.getParentView() && this.getParentView().model) {
                return this.getParentView().model;
            }

            return this.getParentView().getParentView().model;
        },

    })
);