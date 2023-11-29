/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/categories', 'views/record/panels/relationship',
    Dep => Dep.extend({

        rowActionsView: 'pim:views/product/record/row-actions/relationship-categories',

        actionSelectRelatedEntity(data) {
            setTimeout(function () {
                $('.add-filter[data-name=channels]').click();
            }, 750);
        },

        boolFilterData: {
            notEntity() {
                return this.collection.map(model => model.id);
            },
            onlyCatalogCategories() {
                return this.model.get('catalogId');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:relate after:unrelate', link => {
                if (link === 'categories') {
                    $('.action[data-action=refresh][data-panel=productChannels]').click();
                    $('.action[data-action=refresh][data-panel=productAttributeValues]').click();
                }
            });
        },

        actionSetAsMainCategory(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}Category/${data.id}`, {mainCategory: true}).done(entity => {
                this.notify('Updated', 'success');
                this.actionRefresh();
            });
        },

    })
);