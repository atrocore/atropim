/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/detail', 'pim:views/record/detail',
    Dep => Dep.extend({

        template: 'pim:product/record/detail',

        notSavedFields: ['image'],

        isCatalogTreePanel: false,

        showEmptyRequiredFields: true,

        notFilterFields: ['assignedUser', 'ownerUser', 'teams'],

        beforeSaveModel: [],

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() && (this.type === 'detail' || this.type === 'edit') && this.getMetadata().get(['scopes', this.scope, 'advancedFilters'])) {
                this.beforeSaveModel = this.model.getClonedAttributes();
                this.listenTo(this.model, 'main-image-updated', () => {
                    this.applyOverviewFilters();
                });

                this.listenTo(this.model, 'change', () => {
                    this.applyOverviewFilters();
                });

                this.listenTo(this.model, 'after:save', () => {
                    this.beforeSaveModel = this.model.getClonedAttributes();
                    this.applyOverviewFilters();
                });
            }

            this.listenTo(this.model, 'after:save', () => {
                // refresh attributes panels after any saving
                $(".panel-productAttributeValues button[data-action='refresh']").click();
                (this.getMetadata().get('clientDefs.Product.bottomPanels.detail') || []).forEach(tabPanelDefs => {
                    if (tabPanelDefs.tabId) {
                        $(".panel-" + tabPanelDefs.name + " button[data-action='refresh']").click();
                    }
                });

                // refresh categories panel after any saving
                $(".panel-categories button[data-action='refresh']").click();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.isCatalogTreePanel) {
                let observer = new ResizeObserver(() => {
                    this.onTreeResize();
                    observer.unobserve($('#content').get(0));
                });
                observer.observe($('#content').get(0));
            }
        },

        getOverviewFiltersList() {
            let result = Dep.prototype.getOverviewFiltersList.call(this);

            if (this.getAcl().check('Channel', 'read')) {
                this.ajaxGetRequest('Channel', {maxSize: 500}, {async: false}).then(data => {
                    let options = [ "allChannels", "linkedChannels", "Global"];
                    let translatedOptions = {
                        "allChannels": this.translate("allChannels"),
                        "linkedChannels": this.translate('linkedChannels'),
                        "Global": this.translate("Global")
                    };
                    if (data.total > 0) {
                        data.list.forEach(item => {
                            options.push(item.id);
                            translatedOptions[item.id] = item.name;
                        });
                    }
                    result.push({
                        name: "scopeFilter",
                        options: options,
                        translatedOptions: translatedOptions,
                    });

                    if(!this.getStorage().get('scopeFilter', 'OverviewFilter')){
                        this.getStorage().set('scopeFilter', 'OverviewFilter', ['linkedChannels']);
                    }
                });
            }

            return result;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            this.beforeSaveModel = this.model.getClonedAttributes();

            return  data;
        },

        parseRoute(routeStr) {
            let route = [];
            (routeStr || '').split('|').forEach(item => {
                if (item) {
                    route.push(item);
                }
            });

            return route;
        },

        treeLoad(view, treeData) {
            view.clearStorage();

            if (view.treeScope === 'Classification' ) {
                $.ajax({url: `Product/${view.model.get('id')}/classifications`, type: 'GET'}).done(response => {
                    let route = [];
                    view.prepareTreeRoute(treeData, route);
                    if(response.total && response.total > 0) {
                        let $tree = view.getTreeEl();
                        response.list.forEach((classification) =>  {
                            let node = $tree.tree('getNodeById', classification.id);
                            if (node && node.element) {
                                $(node.element).addClass('jqtree-selected');
                            }
                        })
                    }
                });
            }

            if (view.treeScope === 'Category') {
                $.ajax({url: `Product/${view.model.get('id')}/categories?offset=0&sortBy=sortOrder&asc=true`}).done(response => {
                    if (response.total && response.total > 0) {
                        let opened = {};
                        this.selectCategoryNode(response.list, view, opened);
                    }
                });
            }

            if (['Product','Bookmark'].includes(view.treeScope) && view.model && view.model.get('id')) {
                let route = [];
                view.prepareTreeRoute(treeData, route);
            }
        },

        selectCategoryNode(categories, view, opened) {
            if (categories.length > 0) {
                let categoriesRoutes = {};
                categories.forEach(category => {
                    let route = [];
                    this.parseRoute(category.categoryRoute).forEach(id => {
                        if (!opened[id]) {
                            route.push(id);
                        }
                    });

                    categoriesRoutes[category.id] = route;
                });

                let $tree = view.getTreeEl();
                this.ajaxGetRequest('Category/action/TreeData', {ids: Object.keys(categoriesRoutes)}).then(response => {
                    if (response.total && response.total > 0) {
                        (response.tree || []).forEach(node => {
                            let treeData = $tree.tree('getTree').children || [];

                            if (treeData.findIndex(item => item.id === node.id) === -1) {
                                let lastTreeNode = treeData.slice().reverse().find(item => !item.id.includes('show-more'));

                                if (lastTreeNode) {
                                    let lastNode = $tree.tree('getNodeById', lastTreeNode.id);
                                    $tree.tree('addNodeAfter', node, lastNode);
                                }
                            }
                        });

                        Object.keys(categoriesRoutes).forEach(categoryId => {
                            this.openCategoryNodes($tree, categoriesRoutes[categoryId], opened, () => {
                                let node = $tree.tree('getNodeById', categoryId);
                                if (node) {
                                    $tree.tree('addToSelection', node, false);
                                }
                            });
                        });
                    }
                });
            }
        },

        openCategoryNodes($tree, route, opened, callback) {
            if (route.length > 0) {
                let id = route.shift();
                let node = $tree.tree('getNodeById', id);
                $tree.tree('openNode', node, false, () => {
                    opened[id] = true;
                    this.openCategoryNodes($tree, route, opened, callback);
                });
            } else {
                callback();
            }
        },

        hotKeySave: function (e) {
            e.preventDefault();
            if (this.mode === 'edit') {
                this.actionSave();
            } else {
                let viewsFields = this.getFieldViews();
                Object.keys(viewsFields).forEach(item => {
                    if (viewsFields[item].mode === "edit") {
                        viewsFields[item].inlineEditSave();
                    }
                });
            }
        },

        afterNotModified(notShow) {
            if (!notShow) {
                let msg = this.translate('notModified', 'messages');
                Espo.Ui.warning(msg, 'warning');
            }
            this.enableButtons();
        },

        getBottomPanels() {
            let bottomView = this.getView('bottom');
            if (bottomView) {
                return bottomView.nestedViews;
            }
            return null;
        },

        setDetailMode() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].setListMode === 'function') {
                        panels[panel].setListMode();
                    }
                }
            }
            Dep.prototype.setDetailMode.call(this);
        },

        setEditMode() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].setEditMode === 'function') {
                        panels[panel].setEditMode();
                    }
                }
            }
            Dep.prototype.setEditMode.call(this);
        },

        cancelEdit() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].cancelEdit === 'function') {
                        panels[panel].cancelEdit();
                    }
                }
            }
            Dep.prototype.cancelEdit.call(this);
        },

        handlePanelsFetch() {
            let changes = false;
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].panelFetch === 'function') {
                        changes = panels[panel].panelFetch() || changes;
                    }
                }
            }
            return changes;
        },

        validatePanels() {
            let notValid = false;
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].validate === 'function') {
                        notValid = panels[panel].validate() || notValid;
                    }
                }
            }
            return notValid
        },

        handlePanelsSave() {
            let panelsData = {};
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].panelFetch === 'function') {
                        panelsData[panel] = panels[panel].panelFetch();
                    }
                }
            }

            (this.getMetadata().get('clientDefs.Product.bottomPanels.detail') || []).forEach(tabPanelDefs => {
                if (tabPanelDefs.tabId && panelsData[tabPanelDefs.name]) {
                    if (!panelsData['productAttributeValues']) {
                        panelsData['productAttributeValues'] = {};
                    }
                    $.each(panelsData[tabPanelDefs.name], (k, v) => {
                        panelsData['productAttributeValues'][k] = v;
                    });
                    delete panelsData[tabPanelDefs.name];
                }
            });

            return panelsData;
        },

        save(callback, skipExit) {
            (this.notSavedFields || []).forEach(field => {
                const keys = this.getFieldManager().getAttributeList(this.model.getFieldType(field), field);
                keys.forEach(key => delete this.model.attributes[key]);
            });

            this.beforeBeforeSave();

            let data = this.getDataForSave();

            let self = this;
            let model = this.model;

            let initialAttributes = this.attributes;

            let beforeSaveAttributes = this.model.getClonedAttributes();

            let gridInitPackages = false;
            let packageView = false;
            let bottomView = this.getView('bottom');
            if (bottomView) {
                packageView = bottomView.getView('productTypePackages');
                if (packageView) {
                    gridInitPackages = packageView.getInitAttributes();
                }
            }

            let attrs = false;
            let gridPackages = false;
            if (model.isNew()) {
                attrs = data;
            } else {
                for (let name in data) {
                    if (name !== 'id' && gridInitPackages && Object.keys(gridInitPackages).indexOf(name) > -1) {
                        if (!_.isEqual(gridInitPackages[name], data[name])) {
                            (gridPackages || (gridPackages = {}))[name] = data[name];
                        }
                        continue;
                    }

                    if (_.isEqual(initialAttributes[name], data[name])) {
                        continue;
                    }
                    (attrs || (attrs = {}))[name] = data[name];
                }
            }

            let beforeSaveGridPackages = false;
            if (gridPackages && packageView) {
                let gridModel = packageView.getView('grid').model;
                beforeSaveGridPackages = gridModel.getClonedAttributes();
                gridModel.set(gridPackages, {silent: true})
            }

            if (attrs) {
                model.set(attrs, {silent: true});
            }

            const panelsChanges = this.handlePanelsFetch();

            const overviewValidation = this.validate();
            const panelValidation = this.validatePanels();

            if (overviewValidation || panelValidation) {
                if (gridPackages && packageView && beforeSaveGridPackages) {
                    packageView.getView('grid').model.attributes = beforeSaveGridPackages;
                }

                model.attributes = beforeSaveAttributes;

                this.trigger('cancel:save');
                this.afterNotValid();
                return;
            }

            if (gridPackages && packageView) {
                packageView.save();
            }

            let panelsData = this.handlePanelsSave();
            if (panelsData) {
                $.each(panelsData, (panel, panelData) => {
                    if (panelData !== false) {
                        if (!attrs) {
                            attrs = {};
                        }
                        if (!attrs['panelsData']) {
                            attrs['panelsData'] = {};
                        }
                        attrs['panelsData'][panel] = panelData;
                    }
                });
            }

            if (!attrs) {
                this.afterNotModified(gridPackages || panelsChanges);
                this.trigger('cancel:save');
                return true;
            }

            this.beforeSave();

            this.trigger('before:save');
            model.trigger('before:save');

            let _prev = {};
            $.each(attrs, function (field, value) {
                _prev[field] = initialAttributes[field];
            });

            attrs['_prev'] = _prev;
            attrs['_silentMode'] = true;

            let confirmMessage = this.getConfirmMessage(_prev, attrs, model);

            this.notify(false);
            if (confirmMessage) {
                Espo.Ui.confirm(confirmMessage, {
                    confirmText: self.translate('Apply'),
                    cancelText: self.translate('Cancel'),
                    cancelCallback() {
                        self.enableButtons();
                        self.trigger('cancel:save');
                    }
                }, () => {
                    this.saveModel(model, callback, skipExit, attrs);
                });
            } else {
                this.saveModel(model, callback, skipExit, attrs);
            }

            return true;
        },

        hasCompleteness() {
            return this.getMetadata().get(['scopes', this.scope, 'hasCompleteness'])
                && this.getMetadata().get(['entityDefs.Entity.fields.hasCompleteness']);
        },

        onTreeResize(width) {
            if ($('.catalog-tree-panel').length) {
                width = parseInt(width || $('.catalog-tree-panel').outerWidth());

                const content = $('#content');
                const main = content.find('#main');

                const header = content.find('.page-header');
                const btnContainer = content.find('.detail-button-container');
                const filters = content.find('.overview-filters-container');
                const overview = content.find('.overview');
                const side = content.find('.side');

                header.outerWidth(Math.floor(main.width() - width));
                header.css('marginLeft', width + 'px');

                filters.outerWidth(Math.floor(content.get(0).getBoundingClientRect().width - width));
                filters.css('marginLeft', width + 'px');

                btnContainer.outerWidth(Math.floor(content.get(0).getBoundingClientRect().width - width - 1));
                btnContainer.addClass('detail-tree-button-container');
                btnContainer.css('marginLeft', width + 1 + 'px');

                overview.outerWidth(Math.floor(content.outerWidth() - side.outerWidth() - width));
                overview.css('marginLeft', width + 'px');
            }
        }
    })
);

