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

Espo.define('pim:views/product/list', ['pim:views/list', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        template: 'pim:product/list',

        createButton: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#' + this.scope + '/create',
                action: 'quickCreate',
                label: 'Create ' +  this.scope,
                style: 'primary',
                acl: 'create',
                cssStyle: "margin-left: 15px",
                aclScope: this.entityType || this.scope
            });

            if (this.getAcl().check('Catalog', 'read') && this.getAcl().check('Category', 'read')) {
                this.setupCatalogTreePanel();
            }

            this.getStorage().set('list-view', 'Product', 'list');
        },

        setupCatalogTreePanel() {
            this.createView('catalogTreePanel', 'pim:views/product/record/catalog-tree-panel', {
                el: '#main > .catalog-tree-panel',
                scope: this.scope
            }, view => {
                view.listenTo(view, 'select-category', data => this.sortCollectionWithCatalogTree(data));
            });
        },

        sortCollectionWithCatalogTree(data) {
            this.notify('Please wait...');
            this.updateCollectionWithCatalogTree(data);
            this.collection.fetch().then(() => this.notify(false));
        },

        updateCollectionWithCatalogTree(data) {
            data = data || {};
            const defaultFilters = Espo.Utils.cloneDeep(this.searchManager.get());
            const extendedFilters = Espo.Utils.cloneDeep(defaultFilters);

            $.each(data, (key, value) => {
                extendedFilters[key] = _.extend({}, extendedFilters[key], value);
            });

            this.searchManager.set(extendedFilters);
            this.collection.where = this.searchManager.getWhere();
            this.searchManager.set(defaultFilters);
        },

        data() {
            return {
                isCatalogTreePanel: this.getAcl().check('Catalog', 'read') && this.getAcl().check('Category', 'read')
            }
        },

        setupSearchManager() {
            let collection = this.collection;

            var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime(), this.getSearchDefaultData());
            searchManager.scope = this.scope;

            if (this.options.params.showFullListFilter) {
                searchManager.set(_.extend(searchManager.get(), {advanced: Espo.Utils.cloneDeep(this.options.params.advanced)}));
            }

            if ((this.options.params || {}).boolFilterList) {
                searchManager.set({
                    textFilter: '',
                    advanced: {},
                    primary: null,
                    bool: (this.options.params || {}).boolFilterList.reduce((acc, curr) => {
                        acc[curr] = true;
                        return acc;
                    }, {})
                });
            } else {
                searchManager.loadStored();
            }

            collection.where = searchManager.getWhere();
            this.searchManager = searchManager;
        },

        resetSorting() {
            Dep.prototype.resetSorting.call(this);

            let catalogTreePanel = this.getView('catalogTreePanel');
            if (catalogTreePanel) {
                catalogTreePanel.trigger('resetFilters');
            }
        }

    })
);

