/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/products-by-tag', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'Dashlet/ProductsByTag',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('ProductsByTagDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');
                collection.model = collection.model.extend({
                    defs: {
                        fields: {
                            name: {
                                labelMap: this.translate('tag', 'options', 'Product')
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
                                view: 'pim:views/dashlets/fields/colored-varchar-with-url',
                                notSortable: true,
                                width: '60',
                                params: {
                                    filterField: 'tag'
                                }
                            },
                            {
                                name: 'amount',
                                notSortable: true,
                                width: '20'
                            },
                            {
                                name: 'percent',
                                view: 'pim:views/dashlets/fields/percent',
                                notSortable: true,
                                width: '20'
                            }
                        ]
                    }, view => {
                        view.listenTo(view, 'after:render', () => {
                            let amount = 0;
                            collection.each(model => {
                                amount += model.get('amount');
                            });
                            let percent = amount ? '100%' : '0%';
                            view.$el.find('table.table tbody').append(
                                `<tr data-id="total" class="list-row">
                                    <td class="cell" data-name="name" width="60%"><b>${this.translate('Total', 'labels', 'Global')}</b></td>
                                    <td class="cell" data-name="amount" width="20%"><b>${amount}</b></td>
                                    <td class="cell" data-name="percent" width="20%"><b>${percent}</b></td>
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

