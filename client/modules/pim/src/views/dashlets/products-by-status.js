/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
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
                                    filterField: 'productStatus'
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
            let options = this.getMetadata().get(['entityDefs', 'Product', 'fields', 'productStatus', 'options']) || [],
                labels = [];

            if (options.length) {
                options.forEach(item => {
                    labels[item] = this.translate(item, 'labels', 'Product');
                })
            }

            return labels;
        }
    })
);

