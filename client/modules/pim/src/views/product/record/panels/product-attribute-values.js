

Espo.define('pim:views/product/record/panels/product-attribute-values', ['views/record/panels/relationship', 'views/record/panels/bottom', 'search-manager'],
    (Dep, BottomPanel, SearchManager) => Dep.extend({

        template: 'pim:product/record/panels/product-attribute-values',

        baseSelectFields: [
            'channelsIds',
            'channelsNames',
            'data',
            'attributeGroupId',
            'attributeGroupName',
            'attributeId',
            'attributeName',
            'isRequired',
            'productFamilyAttributeId',
            'scope',
            'value',
            'attributeIsMultilang'
        ],

        groupKey: 'attributeGroupId',

        groupLabel: 'attributeGroupName',

        groupScope: 'AttributeGroup',

        groupsWithoutId: ['no_group'],

        noGroup: {
            key: 'no_group',
            label: 'No Group'
        },

        initialAttributes: null,

        boolFilterData: {
            notLinkedProductAttributeValues() {
                return {
                    productId: this.model.id,
                    scope: 'Global'
                }
            }
        },

        events: _.extend({
            'click [data-action="unlinkAttributeGroup"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                let data = $(e.currentTarget).data();
                this.unlinkAttributeGroup(data);
            }
        }, Dep.prototype.events),

        data() {
            return _.extend({
                groups: this.groups,
                groupScope: this.groupScope
            }, Dep.prototype.data.call(this));
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

            var url = this.url || this.model.name + '/' + this.model.id + '/' + this.link;

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

            if (this.defs.create && this.getAcl().check('ProductAttributeValue', 'create')) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.createAction || 'createRelated',
                    link: this.link,
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<span class="fas fa-plus"></span>',
                    data: {
                        link: this.link,
                    }
                });
            }

            if (this.defs.select && this.getAcl().check('ProductAttributeValue', 'create')) {
                var data = {link: this.link};
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }
                data.boolFilterListCallback = 'getSelectBoolFilterList';
                data.boolFilterDataCallback = 'getSelectBoolFilterData';
                data.afterSelectCallback = 'createProductAttributeValue';
                data.scope = 'Attribute';

                this.actionList.unshift({
                    label: 'Select',
                    action: this.defs.selectAction || 'selectRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });

                if (this.getAcl().check('AttributeGroup', 'read')) {
                    this.actionList.push({
                        label: 'Select Attribute Group',
                        action: 'selectAttributeGroup'
                    });
                }
            }

            this.setupActions();

            var layoutName = 'listSmall';
            this.setupListLayout();

            if (this.listLayoutName) {
                layoutName = this.listLayoutName;
            }

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

            this.layoutName = layoutName;
            this.listLayout = listLayout;

            var sortBy = this.defs.sortBy || null;
            var asc = this.defs.asc || null;

            if (this.defs.orderBy) {
                sortBy = this.defs.orderBy;
                asc = true;
                if (this.defs.orderDirection) {
                    if (this.defs.orderDirection && (this.defs.orderDirection === true || this.defs.orderDirection.toLowerCase() === 'DESC')) {
                        asc = false;
                    }
                }
            }

            this.wait(true);
            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = 200;

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
                this.updateBaseSelectFields();
                this.listenTo(this.model, 'updateAttributes change:productFamilyId update-all after:relate after:unrelate', link => {
                    if (!link || link === 'productAttributeValues') {
                        this.actionRefresh();
                    }
                });

                if (this.getMetadata().get(['scopes', this.model.name, 'advancedFilters'])) {
                    this.listenTo(this.model, 'overview-filters-changed', () => {
                        this.applyOverviewFilters();
                    });
                }

                this.getMetadata().fetch();
                this.fetchCollectionGroups(() => this.wait(false));
            }, this);

            this.setupFilterActions();
        },

        updateBaseSelectFields() {
            let inputLanguageList = this.getConfig().get('inputLanguageList') || [];
            if (this.getConfig().get('isMultilangActive') && inputLanguageList.length) {
                inputLanguageList.forEach(lang => {
                    let field = lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), 'value');
                    if (!this.baseSelectFields.includes(field)) {
                        this.baseSelectFields.push(field);
                    }
                });
            }
        },

        createProductAttributeValue(selectObj) {
            let promises = [];
            selectObj.forEach(attributeModel => {
                this.getModelFactory().create(this.scope, model => {
                    model.setRelate({
                        model: this.model,
                        link: this.model.defs.links[this.link].foreign
                    });
                    model.setRelate({
                        model: attributeModel,
                        link: attributeModel.defs.links[this.link].foreign
                    });
                    let attributes = {
                        assignedUserId: this.getUser().id,
                        assignedUserName: this.getUser().get('name'),
                        scope: 'Global'
                    };
                    if (['enum'].includes(attributeModel.get('type'))) {
                        attributes.value = (attributeModel.get('typeValue') || [])[0];
                        if (this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                            let typeValues = this.getFieldManager().getActualAttributeList(attributeModel.get('type'), 'typeValue').splice(1);
                            typeValues.forEach(typeValue => attributes[typeValue.replace('typeValue', 'value')] = (attributeModel.get(typeValue) || [])[0]);
                        }
                    }
                    model.set(attributes);
                    promises.push(model.save());
                });
            });
            Promise.all(promises).then(() => {
                this.notify('Linked', 'success');
                this.actionRefresh();
            });
        },

        actionSelectAttributeGroup() {
            const scope = 'AttributeGroup';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                boolFilterList: ['withNotLinkedAttributesToProduct'],
                boolFilterData: {withNotLinkedAttributesToProduct: this.model.id},
                whereAdditional: [
                    {
                        type: 'isLinked',
                        attribute: 'attributes'
                    }
                ]
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', selectObj => {
                    this.notify('Loading...');
                    if (!Array.isArray(selectObj)) {
                        return;
                    }
                    let boolFilterList = this.getSelectBoolFilterList() || [];
                    this.getFullEntityList('Attribute', {
                        where: [
                            {
                                type: 'bool',
                                value: boolFilterList,
                                data: this.getSelectBoolFilterData(boolFilterList)
                            },
                            {
                                attribute: 'attributeGroupId',
                                type: 'in',
                                value: selectObj.map(model => model.id)
                            }
                        ]
                    }, list => {
                        let models = [];
                        list.forEach(attributes => {
                            this.getModelFactory().create('Attribute', model => {
                                model.set(attributes);
                                models.push(model);
                            });
                        });
                        this.createProductAttributeValue(models);
                    });
                });
            });
        },

        getFullEntityList(url, params, callback, container) {
            if (url) {
                container = container || [];

                let options = params || {};
                options.maxSize = options.maxSize || 200;
                options.offset = options.offset || 0;

                this.ajaxGetRequest(url, options).then(response => {
                    container = container.concat(response.list || []);
                    options.offset = container.length;
                    if (response.total > container.length || response.total === -1) {
                        this.getFullEntity(url, options, callback, container);
                    } else {
                        callback(container);
                    }
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.buildGroups();

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        fetchCollectionGroups(callback) {
            this.collection.data.select = this.getSelectFields().join(',');
            this.collection.reset();
            this.fetchCollectionPart(() => {
                this.groups = [];
                this.groups = this.getGroupsFromCollection();

                let valueKeys = this.groups.map(group => group.key);
                this.getCollectionFactory().create('AttributeGroup', collection => {
                    this.attributeGroupCollection = collection;
                    collection.select = 'sortOrder';
                    collection.maxSize = 200;
                    collection.offset = 0;
                    collection.whereAdditional = [
                        {
                            attribute: 'id',
                            type: 'in',
                            value: valueKeys
                        }
                    ];
                    collection.fetch().then(() => {
                        this.applySortingForAttributeGroups();
                        if (callback) {
                            callback();
                        }
                    });
                });
            });
        },

        applySortingForAttributeGroups() {
            this.groups.forEach(item => {
                let sortOder = 0;
                let attributeGroup = this.attributeGroupCollection.find(model => model.id === item.key);
                if (attributeGroup) {
                    sortOder = attributeGroup.get('sortOrder');
                }
                item.sortOrder = item.sortOrder || sortOder;
            });
            let noGroup = this.groups.find(item => item.key === 'no_group');
            if (noGroup) {
                noGroup.sortOrder = Math.max(...this.groups.map(group => group.sortOrder)) + 1;
            }
            this.groups.sort(function (a, b) {
                return a.sortOrder - b.sortOrder;
            });
        },

        getSelectFields() {
            return this.baseSelectFields || [];
        },

        fetchCollectionPart(callback) {
            this.collection.fetch({remove: false, more: true}).then((response) => {
                if (this.collection.total > this.collection.length) {
                    this.fetchCollectionPart(callback);
                } else if (callback) {
                    callback();
                }
            });
        },

        getGroupsFromCollection() {
            let groups = [];
            this.collection.forEach(model => {
                let params = this.getGroupParams(model);
                this.setGroup(params, model, groups);
            });
            return groups;
        },

        getGroupParams(model) {
            let key = model.get(this.groupKey);
            if (!key) {
                key = this.noGroup.key;
            }
            let label = model.get(this.groupLabel);
            if (!label) {
                label = this.translate(this.noGroup.label, 'labels', 'Product');
            }
            return {
                key: key,
                label: label
            };
        },

        setGroup(params, model, groups) {
            let group = groups.find(item => item.key === params.key);
            if (group) {
                group.editable = group.editable && model.get('isCustom');
                group.rowList.push(model.id);
                group.rowList.sort((a, b) => this.collection.get(a).get('sortOrder') - this.collection.get(b).get('sortOrder'));
            } else {
                groups.push({
                    key: params.key,
                    id: !this.groupsWithoutId.includes(params.key) ? params.key : null,
                    label: params.label,
                    rowList: [model.id],
                    editable: model.get('isCustom')
                });
            }
        },

        buildGroups() {
            let count = 0;
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, collection => {
                    group.rowList.forEach(id => {
                        collection.add(this.collection.get(id));
                    });

                    this.setGroupCollectionDefs(group, collection);

                    this.listenTo(collection, 'sync', () => {
                        this.initialAttributes = this.getInitialAttributes();
                        this.model.trigger('attributes-updated');
                        collection.models.sort((a, b) => a.get('sortOrder') - b.get('sortOrder'));
                        if (this.getMetadata().get(['scopes', this.model.name, 'advancedFilters'])) {
                            this.applyOverviewFilters();
                        }
                    });

                    let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                    let options = {
                        collection: collection,
                        layoutName: this.layoutName,
                        listLayout: this.listLayout,
                        checkboxes: false,
                        rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                        buttonsDisabled: true,
                        el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                        showMore: false
                    };

                    this.createView(group.key, viewName, this.modifyListOptions(options), view => {
                        if (this.getMetadata().get(['scopes', this.model.name, 'advancedFilters'])) {
                            view.listenTo(view, 'after:render', () => this.applyOverviewFilters());
                        }
                        view.render(() => {
                            count++;
                            if (count === this.groups.length) {
                                this.trigger('groups-rendered');
                            }
                        });
                    });
                });
            });
        },

        setGroupCollectionDefs(group, collection) {
            collection.url = `Product/${this.model.id}/productAttributeValues`;
            collection.where = [
                {
                    type: 'bool',
                    value: ['linkedWithAttributeGroup'],
                    data: {
                        linkedWithAttributeGroup: {
                            productId: this.model.id,
                            attributeGroupId: group.key !== 'no_group' ? group.key : null
                        }
                    }
                }
            ];
            collection.data.select = this.getSelectFields().join(',');
        },

        modifyListOptions(options) {
            return options;
        },

        applyOverviewFilters() {
            let currentFieldFilter = (this.model.advancedEntityView || {}).fieldsFilter;
            let attributesWithChannelScope = [];
            let fields = this.getValueFields();
            Object.keys(fields).forEach(name => {
                let fieldView = fields[name];
                let hide = !this.checkFieldValue(currentFieldFilter, fieldView.model.get(fieldView.name), fieldView.model.get('isRequired'));
                if (!hide){
                    hide = this.updateCheckByChannelFilter(fieldView, attributesWithChannelScope);
                }
                if (!hide && fieldView.model.get('attributeIsMultilang')){
                    hide = this.updateCheckByLocaleFilter(fieldView, currentFieldFilter);
                }
                this.controlRowVisibility(fieldView, name, hide);
            });
            this.hideChannelAttributesWithGlobalScope(fields, attributesWithChannelScope);
        },

        updateCheckByChannelFilter(fieldView, attributesWithChannelScope) {
            let hide = false;
            let currentChannelFilter = (this.model.advancedEntityView || {}).channelsFilter;
            if (currentChannelFilter) {
                if (currentChannelFilter === 'onlyGlobalScope') {
                    hide = fieldView.model.get('scope') !== 'Global';
                } else {
                    hide = (fieldView.model.get('scope') === 'Channel' && !(fieldView.model.get('channelsIds') || []).includes(currentChannelFilter));
                    if ((fieldView.model.get('channelsIds') || []).includes(currentChannelFilter)) {
                        attributesWithChannelScope.push(fieldView.model.get('attributeId'));
                    }
                }
            }

            return hide;
        },

        updateCheckByLocaleFilter(fieldView, currentFieldFilter) {
            // get filter
            let filter = (this.model.advancedEntityView || {}).localesFilter;

            return filter !== null && filter !== '' && !fieldView.model.get('attributeIsMultilang');
        },

        getValueFields() {
            let fields = {};
            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView) {
                    groupView.rowList.forEach(row => {
                        let rowView = groupView.getView(row);
                        if (rowView) {
                            let containerView = rowView.getView('valueField');
                            if (containerView) {
                                let fieldView = containerView.getView('valueField');
                                fieldView.groupKey = group.key;
                                fields[row] = fieldView;
                            }
                        }
                    });
                }
            });
            return fields;
        },

        checkFieldValue(currentFieldFilter, value, required) {
            let check = !currentFieldFilter;
            if (currentFieldFilter === 'empty') {
                check = value === null || value === '' || (Array.isArray(value) && !value.length);
            }
            if (currentFieldFilter === 'emptyAndRequired') {
                check = (value === null || value === '' || (Array.isArray(value) && !value.length)) && required;
            }

            return check;
        },

        controlRowVisibility(fieldView, rowId, hide) {
            let groupView = this.getView(fieldView.groupKey);
            let rowView = groupView.getView(rowId);
            if (hide) {
                rowView.$el.addClass('hidden');
            } else {
                rowView.$el.removeClass('hidden');
            }
            this.controlGroupVisibility(groupView);
        },

        controlGroupVisibility(groupView) {
            if (groupView.$el.find('.list-row.hidden').size() === (groupView.rowList || []).length) {
                groupView.$el.parent().addClass('hidden');
            } else {
                groupView.$el.parent().removeClass('hidden');
            }
        },

        hideChannelAttributesWithGlobalScope(fields, attributesWithChannelScope) {
            Object.keys(fields).forEach(name => {
                let fieldView = fields[name];
                if (attributesWithChannelScope.includes(fieldView.model.get('attributeId')) && fieldView.model.get('scope') === 'Global') {
                    this.controlRowVisibility(fieldView, name, true);
                }
            });
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
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

        actionRefresh() {
            Object.keys(this.nestedViews).forEach(view => this.clearView(view));
            this.getMetadata().fetch();
            this.fetchCollectionGroups(() => {
                this.reRender();
            });
        },

        unlinkAttributeGroup(data) {
            let id = data.id;
            if (!id) {
                return;
            }

            let group = this.groups.find(group => group.id === id);
            if (!group || !group.rowList) {
                return;
            }

            this.confirm({
                message: this.translate('unlinkAttributeGroupConfirmation', 'messages', 'AttributeGroup'),
                confirmText: this.translate('Unlink')
            }, function () {
                this.notify('Unlinking...');
                $.ajax({
                    url: `${this.model.name}/${this.link}/relation`,
                    data: JSON.stringify({
                        ids: [this.model.id],
                        foreignIds: group.rowList
                    }),
                    type: 'DELETE',
                    contentType: 'application/json',
                    success: function () {
                        this.notify('Unlinked', 'success');
                        this.model.trigger('after:unrelate');
                        this.actionRefresh();
                    }.bind(this),
                    error: function () {
                        this.notify('Error occurred', 'error');
                    }.bind(this),
                });
            }, this);
        },

        setListMode() {
            this.mode = 'list';

            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView) {
                    groupView.setListMode();
                }
            });

            this.reRender();
        },

        setEditMode() {
            this.initialAttributes = this.getInitialAttributes();

            const groupsRendered = this.groups.every(group => {
                const groupView = this.getView(group.key);
                return groupView && groupView.isRendered();
            });

            const updateMode = () => {
                this.mode = 'edit';
                this.groups.forEach(group => {
                    let groupView = this.getView(group.key);
                    if (groupView) {
                        groupView.setEditMode();
                    }
                });
            };

            if (groupsRendered) {
                updateMode();
            } else {
                this.listenToOnce(this, 'groups-rendered', () => updateMode());
            }
        },

        cancelEdit() {
            this.actionRefresh();
        },

        getInitialAttributes() {
            const data = {};
            this.collection.forEach(model => {
                const modelData = {
                    value: model.get('value')
                };
                const actualFields = this.getFieldManager().getActualAttributeList(model.get('attributeType'), 'value');
                actualFields.forEach(field => {
                    if (model.has(field)) {
                        _.extend(modelData, {[field]: model.get(field)});
                    }
                });
                const additionalData = model.get('data');
                if (additionalData) {
                    modelData.data = additionalData;
                }
                data[model.id] = Espo.Utils.cloneDeep(modelData);
            });
            return data;
        },

        panelFetch() {
            let data = false;
            this.groups.forEach(group => {
                const groupView = this.getView(group.key);
                (groupView.rowList || []).forEach(id => {
                    const row = groupView.getView(id);
                    const value = row.getView('valueField');
                    if (value.mode === 'edit') {
                        const fetchedData = value.fetch();
                        const initialData = this.initialAttributes[id];
                        value.model.set(fetchedData);
                        if (this.equalityValueCheck(fetchedData, initialData)) {
                            data = _.extend(data || {}, {[id]: fetchedData});
                        }
                    }
                });
            });
            return data;
        },

        equalityValueCheck(fetchedData, initialData) {
            return !_.isEqual(initialData, fetchedData) && (Object.keys(fetchedData).every(key => {
                const initial = initialData[key];
                const fetched = fetchedData[key];
                return (Array.isArray(initial) ? initial.length : initial) || (Array.isArray(fetched) ? fetched.length : fetched);
            }));
        },

        save() {
            const data = this.panelFetch();
            if (data) {
                const promises = [];
                $.each(data, (id, attrs) => {
                    this.collection.get(id).set(attrs, {silent: true});
                    promises.push(this.ajaxPutRequest(`${this.collection.name}/${id}`, attrs))
                });
                this.notify('Saving...');
                Promise.all(promises)
                    .then(response => {
                        this.notify('Saved', 'success');
                        this.model.trigger('after:attributesSave');
                    }, error => {
                        this.actionRefresh();
                    });
            }
        },

        validate() {
            this.trigger('collapsePanel', 'show');

            let notValid = false;
            this.groups.forEach(group => {
                const groupView = this.getView(group.key);
                (groupView.rowList || []).forEach(id => {
                    const row = groupView.getView(id);
                    const value = row.getView('valueField');
                    if (value.mode === 'edit' && !value.disabled && !value.readOnly) {
                        notValid = value.validate() || notValid;
                    }
                });
            });
            return notValid;
        }

    })
);