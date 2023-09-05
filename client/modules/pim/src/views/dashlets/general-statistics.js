/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/general-statistics', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'Dashlet/GeneralStatistics',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('GeneralStatisticsDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');
                collection.model = collection.model.extend({
                    defs: {
                        fields: {
                            name: {
                                urlMap: this.getOption('urlMap'),
                                labelMap: this.translate('generalStatistics', 'listFields', 'GeneralStatisticsDashlet')
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
                                width: '80'
                            },
                            {
                                name: 'amount',
                                notSortable: true,
                                width: '20'
                            }
                        ]
                    }, view => {
                        view.render();
                    });
                }.bind(this));
                collection.fetch();

            }, this);
        },

    })
);

