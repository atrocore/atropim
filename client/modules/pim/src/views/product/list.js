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

Espo.define('pim:views/product/list', ['pim:views/category/list', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        createButton: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#' + this.scope + '/create',
                action: 'quickCreate',
                label: 'Create ' + this.scope,
                style: 'primary',
                acl: 'create',
                cssStyle: "margin-left: 15px",
                aclScope: this.entityType || this.scope
            });
        },

        isTreeAllowed() {
            let result = false;

            this.getMetadata().get('clientDefs.Product.treeScopes').forEach(scope => {
                if (this.getAcl().check(scope, 'read')) {
                    result = true;
                    if (!localStorage.getItem('treeScope')) {
                        localStorage.setItem('treeScope', scope);
                    }
                }
            })

            return result;
        },

        setupTreePanel() {
            if (!this.isTreeAllowed()) {
                return;
            }

            Dep.prototype.setupTreePanel.call(this);
        },

        resetSorting() {
            Dep.prototype.resetSorting.call(this);

            localStorage.removeItem('selectedNodeId');
            localStorage.removeItem('selectedNodeRoute');

            this.getView('treePanel').buildTree();
        },

        selectNode(data) {
            localStorage.setItem('selectedNodeId', data.id);
            localStorage.setItem('selectedNodeRoute', data.route);

            const $treeView = this.getView('treePanel');
            $treeView.selectTreeNode($treeView.parseRoute(data.route), data.id);
            this.notify('Please wait...');
            this.updateCollectionWithTree(data.id);
            this.collection.fetch().then(() => this.notify(false));
        },

        treeInit(view) {
            if (localStorage.getItem('selectedNodeId')) {
                view.selectTreeNode(view.parseRoute(localStorage.getItem('selectedNodeRoute')), localStorage.getItem('selectedNodeId'));

                this.notify('Please wait...');
                this.updateCollectionWithTree(localStorage.getItem('selectedNodeId'));
                this.collection.fetch().then(() => this.notify(false));
            }
        },

        treeReset(view) {
            this.notify('Please wait...');

            localStorage.removeItem('selectedNodeId');
            localStorage.removeItem('selectedNodeRoute');

            view.buildTree();
            this.updateCollectionWithTree(null);
            this.collection.fetch().then(() => this.notify(false));
        },

        updateCollectionWithTree(id) {
            let data = {bool: {}, boolData: {}};

            const filterName = "linkedWith" + localStorage.getItem('treeScope');

            data['bool'][filterName] = true;
            data['boolData'][filterName] = id;

            const defaultFilters = Espo.Utils.cloneDeep(this.searchManager.get());
            const extendedFilters = Espo.Utils.cloneDeep(defaultFilters);

            $.each(data, (key, value) => {
                extendedFilters[key] = _.extend({}, extendedFilters[key], value);
            });

            this.searchManager.set(extendedFilters);
            this.collection.where = this.searchManager.getWhere();
            this.searchManager.set(defaultFilters);
        },

    })
);