/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/products-by-status', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'Dashlet/ProductsByStatus',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('ProductsByStatusDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');
                collection.model = collection.model.extend({
                    defs: {
                        fields: {
                            name: {
                                labelMap: this.prepareStatusLabel()
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
                                    filterField: 'status'
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
                            let amount = 0
                            collection.each(model => {
                                amount += model.get('amount');
                            });
                            view.$el.find('table.table tbody').append(
                                `<tr data-id="total" class="list-row">
                                    <td class="cell" data-name="name" width="60%"><b>${this.translate('Total', 'labels', 'Global')}</b></td>
                                    <td class="cell" data-name="amount" width="20%"><b>${amount}</b></td>
                                    <td class="cell" data-name="percent" width="20%"><b>100%</b></td>
                                </tr>'`
                            );
                        });

                        view.render();
                    });
                }.bind(this));
                collection.fetch();

            }, this);
        },

        prepareStatusLabel: function () {
            let options = this.getMetadata().get(['entityDefs', 'Product', 'fields', 'status', 'options']) || [],
                labels = [];

            if (options.length) {
                options.forEach(item => {
                    labels[item] = this.getLanguage().translateOption(item, 'status', 'Product');
                })
            }

            return labels;
        }
    })
);

