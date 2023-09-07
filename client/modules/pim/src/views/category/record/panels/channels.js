/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/category/record/panels/channels', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            notEntity() {
                return this.collection.map(model => model.id);
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', () => {
                this.reRender();
            });
        },

    })
);
