/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/category/record/panels/products', 'views/record/panels/relationship',
    (Dep) => Dep.extend({

        boolFilterData: {
            notEntity() {
                return this.collection.map(model => model.id);
            },
            onlyCategoryCatalogsProducts() {
                return this.model.get('id');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.collection, 'listSorted', () => {
                this.collection.fetched = false
            });
        },

        actionQuickView: function (data) {
            if (this.collection.fetched === false) {
                this.collection.fetched = true;
                this.collection.fetch();

            }
        },

        actionQuickEdit: function (data) {
            this.actionQuickView(data);
        }
    })
);
