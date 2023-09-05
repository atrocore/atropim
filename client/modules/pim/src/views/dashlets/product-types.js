/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/product-types', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'Dashlet/ProductTypes',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('ProductTypesDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');
                collection.model = collection.model.extend({
                    defs: {
                        fields: {
                            name: {
                                labelMap: this.translate('type', 'options', 'Product')
                            }
                        }
                    }
                });

                this.listenToOnce(collection, 'sync', function () {
                    this.createView('list', 'views/record/list', {
                        el: this.getSelector() + ' > .list-container',
                        collection: collection,
                        rowActionsDisabled: true,
                        checkboxes: false,
                        listLayout: [
                            {
                                name: 'name',
                                view: 'pim:views/dashlets/fields/varchar-with-url',
                                notSortable: true,
                                width: '40'
                            },
                            {
                                name: 'total',
                                notSortable: true,
                                width: '20'
                            },
                            {
                                name: 'active',
                                notSortable: true,
                                width: '20'
                            },
                            {
                                name: 'notActive',
                                notSortable: true,
                                width: '20'
                            }
                        ]
                    }, view => {
                        view.listenTo(view, 'after:render', () => {
                            let total = 0;
                            let active = 0;
                            let notActive = 0;
                            collection.each(model => {
                                total += model.get('total');
                                active += model.get('active');
                                notActive += model.get('notActive');
                            });
                            view.$el.find('table.table tbody').append(
                                `<tr data-id="total" class="list-row">
                                    <td class="cell" data-name="name" width="40%"><b>${this.translate('Total', 'labels', 'Global')}</b></td>
                                    <td class="cell" data-name="total" width="20%"><b>${total}</b></td>
                                    <td class="cell" data-name="active" width="20%"><b>${active}</b></td>
                                    <td class="cell" data-name="notActive" width="20%"><b>${notActive}</b></td>
                                </tr>'`
                            );
                        });

                        view.render();
                    });
                }.bind(this));
                collection.fetch();

            }, this);
        },

    })
);

