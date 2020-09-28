

Espo.define('pim:views/product/record/panels/product-categories', ['views/record/panels/relationship', 'views/record/panels/bottom', 'search-manager'],
    (Dep, BottomPanel, SearchManager) => Dep.extend({

        boolFilterData: {
            notLinkedProductCategories() {
                return {
                    productId: this.model.id,
                    scope: 'Global'
                };
            },
            onlyCatalogCategories() {
                return this.model.get('catalogId');
            }
        },

        setup() {
            let bottomPanel = new BottomPanel();
            bottomPanel.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

            if (!this.scope && !(this.link in this.model.defs.links)) {
                throw new Error('Link \'' + this.link + '\' is not defined in model \'' + this.model.name + '\'');
            }
            this.title = this.title || this.translate(this.link, 'links', this.model.name);
            this.scope = this.scope || this.model.defs.links[this.link].entity;

            var url = this.url || this.model.name + '/' + this.model.id + '/' + this.link;

            if (!this.getConfig().get('scopeColorsDisabled')) {
                var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
                if (iconHtml) {
                    if (this.defs.label) {
                        this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', this.scope);
                    } else {
                        this.titleHtml = iconHtml + this.title;
                    }
                }
            }

            if (!this.getAcl().check('Category', 'create') || !this.getAcl().check('ProductCategory', 'create')) {
                this.readOnly = true;
            }

            if (!this.readOnly && !this.defs.readOnly) {
                if (!('create' in this.defs)) {
                    this.defs.create = true;
                }
                if (!('select' in this.defs)) {
                    this.defs.select = true;
                }
            }

            this.filterList = this.defs.filterList || this.filterList || null;

            if (this.filterList && this.filterList.length) {
                this.filter = this.getStoredFilter();
            }

            if (this.defs.create) {
                if (this.getAcl().check(this.scope, 'create')) {
                    this.buttonList.push({
                        title: 'Create',
                        action: this.defs.createAction || 'createRelated',
                        link: this.link,
                        acl: 'create',
                        aclScope: this.scope,
                        html: '<span class="fas fa-plus"></span>',
                        data: {
                            link: this.link,
                            layout: this.defs.detailLayout
                        }
                    });
                }
            }

            if (this.defs.select) {
                var data = {link: this.link};
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }
                data.boolFilterListCallback = 'getSelectBoolFilterList';
                data.boolFilterDataCallback = 'getSelectBoolFilterData';
                data.afterSelectCallback = 'createProductCategory';
                data.scope = 'Category';

                this.actionList.unshift({
                    label: 'Select',
                    action: this.defs.selectAction || 'selectRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });
            }

            this.setupActions();

            var layoutName = 'listSmall';
            var listLayout = null;
            var layout = this.defs.layout || null;
            if (layout) {
                if (typeof layout == 'string') {
                    layoutName = layout;
                } else {
                    layoutName = 'listRelationshipCustom';
                    listLayout = layout;
                }
            }
            var sortBy = this.defs.sortBy || null;
            var asc = this.defs.asc || null;

            this.wait(true);
            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.defs.recordsPerPage || this.getConfig().get('recordsPerPageSmall') || 5;

                if (this.defs.filters) {
                    var searchManager = new SearchManager(collection, 'listRelationship', false, this.getDateTime());
                    searchManager.setAdvanced(this.defs.filters);
                    collection.where = searchManager.getWhere();
                }

                collection.url = collection.urlRoot = url;
                if (sortBy) {
                    collection.sortBy = sortBy;
                }
                if (asc) {
                    collection.asc = asc;
                }
                this.collection = collection;

                this.setFilter(this.filter);

                if (this.fetchOnModelAfterRelate) {
                    this.listenTo(this.model, 'after:relate', function () {
                        collection.fetch();
                    }, this);
                }

                if (this.getMetadata().get(['scopes', this.model.name, 'advancedFilters'])) {
                    this.listenTo(this.model, 'overview-filters-changed', () => {
                        this.applyOverviewFilters();
                    });
                }

                var viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                this.once('after:render', function () {
                    this.createView('list', viewName, {
                        collection: collection,
                        layoutName: layoutName,
                        listLayout: listLayout,
                        checkboxes: false,
                        rowActionsView: this.defs.readOnly || this.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                        buttonsDisabled: true,
                        el: this.options.el + ' .list-container',
                        skipBuildRows: true
                    }, function (view) {
                        view.getSelectAttributeList(function (selectAttributeList) {
                            if (selectAttributeList) {
                                collection.data.select = selectAttributeList.join(',');
                            }
                            collection.fetch();
                        }.bind(this));
                        if (this.getMetadata().get(['scopes', this.model.name, 'advancedFilters'])) {
                            view.listenTo(view, 'after:render', () => {
                                this.applyOverviewFilters();
                            });
                        }
                    });
                }, this);

                this.wait(false);
            }, this);

            this.setupFilterActions();
        },

        applyOverviewFilters() {
            let rows = this.getListRows();
            let categoriesWithChannelScope = [];
            Object.keys(rows).forEach(name => {
                let row = rows[name];
                this.controlRowVisibility(row, this.updateCheckByChannelFilter(row, categoriesWithChannelScope));
            });
            this.hideChannelCategoriesWithGlobalScope(rows, categoriesWithChannelScope);
        },

        updateCheckByChannelFilter(row, categoriesWithChannelScope) {
            let hide = false;
            let currentChannelFilter = (this.model.advancedEntityView || {}).channelsFilter;
            if (currentChannelFilter) {
                if (currentChannelFilter === 'onlyGlobalScope') {
                    hide = row.model.get('scope') !== 'Global';
                } else {
                    hide = (row.model.get('scope') === 'Channel' && !(row.model.get('channelsIds') || []).includes(currentChannelFilter));
                    if ((row.model.get('channelsIds') || []).includes(currentChannelFilter)) {
                        categoriesWithChannelScope.push(row.model.get('categoryId'));
                    }
                }
            }
            return hide;
        },

        controlRowVisibility(row, hide) {
            if (hide) {
                row.$el.addClass('hidden');
            } else {
                row.$el.removeClass('hidden');
            }
        },

        getListRows() {
            let fields = {};
            let list = this.getView('list');
            if (list) {
                for (let row in list.nestedViews || {}) {
                    let rowView = list.getView(row);
                    if (rowView) {
                        fields[row] = rowView;
                    }
                }
            }
            return fields;
        },

        hideChannelCategoriesWithGlobalScope(rows, categoriesWithChannelScope) {
            Object.keys(rows).forEach(name => {
                let row = rows[name];
                if (categoriesWithChannelScope.includes(row.model.get('categoryId')) && row.model.get('scope') === 'Global') {
                    this.controlRowVisibility(row, true);
                }
            });
        },

        createProductCategory(selectObj) {
            let promises = [];
            selectObj.forEach(categoryModel => {
                this.getModelFactory().create(this.scope, model => {
                    model.setRelate({
                        model: this.model,
                        link: this.model.defs.links[this.link].foreign
                    });
                    model.setRelate({
                        model: categoryModel,
                        link: categoryModel.defs.links[this.link].foreign
                    });
                    model.set({
                        assignedUserId: this.getUser().id,
                        assignedUserName: this.getUser().get('name'),
                        scope: 'Global'
                    });
                    promises.push(model.save());
                });
            });
            Promise.all(promises).then(() => {
                this.notify('Linked', 'success');
                this.actionRefresh();
            });
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

        getSelectBoolFilterData(boolFilterList) {
            let data = {};
            if (Array.isArray(boolFilterList)) {
                boolFilterList.forEach(item => {
                    if (this.boolFilterData && typeof this.boolFilterData[item] === 'function') {
                        data[item] = this.boolFilterData[item].call(this);
                    }
                });
            }
            return data;
        }

    })
);
