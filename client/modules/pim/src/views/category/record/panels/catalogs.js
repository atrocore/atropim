/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/category/record/panels/catalogs', 'views/record/panels/relationship',
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

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let $create = $('.panel-catalogs .action[data-action=createRelated][data-panel=catalogs]');
            let $dropdown = $('.panel-catalogs .dropdown-toggle');

            if (this.model.get('categoryParentId')) {
                $create.hide();
                $dropdown.hide();
            } else {
                $create.show();
                $dropdown.show();
            }
        },

    })
);
