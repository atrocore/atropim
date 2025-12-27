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

        data() {
            let data = Dep.prototype.data.call(this);
            this.beforeSaveModel = this.model.getClonedAttributes();

            return data;
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

        treeLoad(treeScope) {
            if (treeScope === 'Classification') {
                $.ajax({ url: `Product/${this.model.get('id')}/classifications`, type: 'GET' }).done(response => {
                    if (response.total && response.total > 0) {
                        let $tree = window.treePanelComponent.getTreeEl();
                        response.list.forEach((classification) => {
                            let node = $tree.tree('getNodeById', classification.id);
                            if (node && node.element) {
                                $(node.element).addClass('jqtree-selected');
                            }
                        })
                    }
                });
            }

            if (treeScope === 'Brand' && this.model.get('brandId')) {
                setTimeout(() => {
                    let $tree = window.treePanelComponent.getTreeEl();
                    let node = $tree.tree('getNodeById', this.model.get('brandId'));
                    if (node && node.element) {
                        $(node.element).addClass('jqtree-selected');
                    }
                }, 300)
            }

            if (treeScope === 'Category') {
                $.ajax({ url: `Product/${this.model.get('id')}/categories?offset=0&sortBy=sortOrder&asc=true` }).done(response => {
                    if (response.total && response.total > 0) {
                        this.selectCategoryNode(response.list);
                    }
                });
            }
        },

        selectCategoryNode(categories) {
            if (categories.length > 0) {
                const categoriesRoutes = [];
                const ids = []
                categories.forEach(category => {
                    const routes = category.routes || []
                    routes.forEach(item => {
                        let route = [];
                        this.parseRoute(item).forEach(id => {
                            route.push(id);
                        });
                        categoriesRoutes.push({ id: category.id, route: route });
                    });

                    if (routes.length === 0) {
                        categoriesRoutes.push({ id: category.id, route: [] });
                    }

                    ids.push(category.id);
                });


                let $tree = window.treePanelComponent.getTreeEl();
                this.ajaxGetRequest('Category/action/TreeData', { ids: ids }).then(response => {
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

                        categoriesRoutes.forEach(({ id, route }) => {
                            this.openCategoryNodes($tree, route, null, (node) => {
                                if (node) {
                                    node.children.forEach(child => {
                                        if (child.id === id) {
                                            $tree.tree('addToSelection', child, false);
                                        }
                                    });
                                } else {
                                    // if root node
                                    node = $tree.tree('getNodeById', id);
                                    if (node) {
                                        $tree.tree('addToSelection', node, false);
                                    }
                                }
                            });
                        });
                    }
                });
            }
        },

        openCategoryNodes($tree, route, lastNode, callback) {
            if (route.length > 0) {
                let id = route.shift();
                let node = $tree.tree('getNodeById', id);
                const isOpened = ($tree.tree('getState').open_nodes || []).includes(id);

                const onOpened = (lastNode) => {
                    this.openCategoryNodes($tree, route, lastNode, callback);
                }

                if (isOpened) {
                    onOpened(node)
                } else {
                    $tree.tree('openNode', node, false, onOpened);
                }
            } else {
                callback(lastNode);
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
                gridModel.set(gridPackages, { silent: true })
            }

            if (attrs) {
                model.set(attrs, { silent: true });
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

        onTreeResize(width) {

        }
    })
);

