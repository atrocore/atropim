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

Espo.define('pim:views/product/record/panels/product-attribute-values', ['views/record/panels/relationship', 'views/record/panels/bottom', 'search-manager'],
    (Dep, BottomPanel, SearchManager) => Dep.extend({

        template: 'pim:product/record/panels/product-attribute-values',

        baseSelectFields: [
            'channelId',
            'channelName',
            'data',
            'attributeGroupId',
            'attributeGroupName',
            'attributeId',
            'attributeName',
            'attributeTooltip',
            'isRequired',
            'scope',
            'value',
            'attributeIsMultilang',
            'isInheritAssignedUser',
            'isInheritOwnerUser',
            'isInheritTeams',
            'isVariantSpecificAttribute'
        ],

        scope: 'ProductAttributeValue',

        groupKey: 'attributeGroupId',

        groupLabel: 'attributeGroupName',

        groupsWithoutId: ['no_group'],

        noGroup: {
            key: 'no_group',
            label: 'noGroup'
        },

        initialAttributes: null,

        boolFilterData: {
            fromAttributesTab() {
                return {
                    tabId: this.defs.tabId
                }
            },
            onlyDefaultChannelAttributes() {
                return {
                    productId: this.model.id
                }
            }
        },

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

            this.filterList = this.defs.filterList || this.filterList || null;

            if (this.filterList && this.filterList.length) {
                this.filter = this.getStoredFilter();
            }

            if (this.getAcl().check('ProductAttributeValue', 'create')) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.createAction || 'createRelated',
                    link: this.link,
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<span class="fas fa-plus"></span>',
                    data: {
                        link: this.link,
                        tabId: this.defs.tabId
                    }
                });
            }

            if (this.getAcl().check('ProductAttributeValue', 'create')) {
                var data = {link: this.defs.name};
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
                        label: 'selectAttributeGroup',
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
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;

                this.setFilter(this.filter);

                this.listenTo(this.model, 'updateAttributes change:classificationId update-all after:relate after:unrelate', link => {
                    if (!link || link === 'productAttributeValues') {
                        this.getCollectionFactory().create(this.scope, collection => {
                            this.collection = collection;
                            this.actionRefresh();
                        });
                    }
                });

                this.listenTo(this.model, 'overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });

                this.fetchCollectionGroups(() => {
                    this.wait(false);
                });
            });
        },

        createProductAttributeValue(selectObj) {
            let promises = [];
            selectObj.forEach(item => {
                this.getModelFactory().create(this.scope, model => {
                    let attributes = {
                        productId: this.model.get('id'),
                        attributeId: item.id,
                        assignedUserId: this.getUser().id,
                        assignedUserName: this.getUser().get('name')
                    };

                    model.set(attributes);
                    promises.push(model.save());
                });
            });
            Promise.all(promises).then(() => {
                this.notify('Linked', 'success');
                this.model.trigger('after:relate', this.link, this.defs);
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
                boolFilterList: ['withNotLinkedAttributesToProduct', 'fromAttributesTab'],
                boolFilterData: {
                    withNotLinkedAttributesToProduct: this.model.id,
                    fromAttributesTab: {tabId: this.defs.tabId}
                },
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
            this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', {
                tabId: this.defs.tabId,
                productId: this.model.get('id')
            }).then(data => {
                this.groups = data;
                callback();
            });
        },

        getSelectFields() {
            return this.baseSelectFields || [];
        },

        buildGroups() {
            if (!this.groups || this.groups.length < 1) {
                return;
            }

            let count = 0;
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, groupCollection => {
                    groupCollection.url = 'Product/' + this.model.id + '/productAttributeValues';
                    groupCollection.data.select = this.getSelectFields().join(',');
                    groupCollection.data.tabId = this.defs.tabId;
                    groupCollection.where = [
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
                    groupCollection.maxSize = 9999;
                    groupCollection.fetch().success(() => {
                        group.rowList.forEach(id => {
                            if (this.collection.get(id)) {
                                this.collection.remove(id);
                            }
                            this.collection.add(groupCollection.get(id));
                        });

                        let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                        let options = {
                            collection: groupCollection,
                            layoutName: this.layoutName,
                            listLayout: this.listLayout,
                            checkboxes: false,
                            rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                            buttonsDisabled: true,
                            el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                            showMore: false,
                            groupId: group.id,
                            groupName: group.label
                        };

                        this.createView(group.key, viewName, options, view => {
                            view.listenTo(view, 'after:render', () => this.applyOverviewFilters());
                            view.listenTo(view, 'remove-attribute-group', (data) => this.unlinkAttributeGroup(data));
                            view.listenTo(view, 'remove-attribute-group-hierarchically', (data) => this.unlinkAttributeGroupHierarchy(data));

                            view.render(() => {
                                count++;
                                if (count === this.groups.length) {
                                    this.initialAttributes = this.getInitialAttributes();
                                    this.applyOverviewFilters();
                                    this.trigger('groups-rendered');
                                }
                            });
                        });
                    });
                });
            });
        },

        applyOverviewFilters() {
            const fieldFilter = this.getStorage().get('fieldFilter', 'OverviewFilter') || ['allValues'];
            const languageFilter = this.getStorage().get('languageFilter', 'OverviewFilter') || ['allLanguages'];
            const scopeFilter = this.getStorage().get('scopeFilter', 'OverviewFilter') || ['allChannels'];

            $.each(this.getValueFields(), (name, fieldView) => {
                let value = fieldView.model.get('value'),
                    hide = false;

                if (!fieldFilter.includes('allValues')) {
                    // hide filled
                    if (!hide && fieldFilter.includes('filled')) {
                        hide = this.isEmptyValue(value);
                    }

                    // hide empty
                    if (!hide && fieldFilter.includes('empty')) {
                        hide = !this.isEmptyValue(value);
                    }

                    // hide optional
                    if (!hide && fieldFilter.includes('optional')) {
                        hide = this.isRequiredValue(fieldView);
                    }

                    // hide required
                    if (!hide && fieldFilter.includes('required')) {
                        hide = !this.isRequiredValue(fieldView);
                    }
                }

                if (!scopeFilter.includes('allChannels')) {
                    // hide channel
                    if (!hide && !this.isScopeValid(fieldView, scopeFilter)) {
                        hide = true;
                    }
                }

                // for languages
                if (!languageFilter.includes('allLanguages')) {
                    if (!hide && this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                        let attributeLanguage = fieldView.model.get('language') || 'main';
                        if (!languageFilter.includes(attributeLanguage)) {
                            hide = true;
                        }
                    }
                }

                this.controlRowVisibility(fieldView, name, hide);
            });
        },

        isEmptyValue(value) {
            return value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        isRequiredValue(view) {
            return view.model.get('isRequired') || false
        },

        isScopeValid(view, channels) {
            const scope = view.model.get('scope');

            let channelId = view.model.get('channelId') || 'Global';

            if (scope === 'Global') {
                if (!channels.includes(channelId)) {
                    let hasChannelAttr = false;

                    $.each(this.getValueFields(), (n, f) => {
                        if (f.model.get('attributeId') === view.model.get('attributeId') && f.model.get('scope') === 'Channel' && channels.includes(f.model.get('channelId'))) {
                            hasChannelAttr = true;
                        }
                    });

                    if (hasChannelAttr) {
                        return false;
                    }
                }
            } else if (scope === 'Channel') {
                if (!channels.includes(channelId)) {
                    return false;
                }
            }

            return true
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
                                if (fieldView) {
                                    fieldView.groupKey = group.key;
                                    fields[row] = fieldView;
                                }
                            }
                        }
                    });
                }
            });
            return fields;
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
                message: this.translate('removeRelatedAttributeGroup', 'messages', 'ProductAttributeValue'),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('removing');
                $.ajax({
                    url: `${this.model.name}/${this.link}/relation`,
                    data: JSON.stringify({
                        ids: [this.model.id],
                        foreignIds: group.rowList
                    }),
                    type: 'DELETE',
                    contentType: 'application/json',
                    success: function () {
                        this.notify('Removed', 'success');
                        this.model.trigger('after:unrelate', this.link, this.defs);
                        this.actionRefresh();
                    }.bind(this),
                    error: function () {
                        this.notify('Error occurred', 'error');
                    }.bind(this),
                });
            }, this);
        },

        unlinkAttributeGroupHierarchy(data) {
            let id = data.id;
            if (!id) {
                return;
            }

            let group = this.groups.find(group => group.id === id);
            if (!group || !group.rowList) {
                return;
            }

            this.confirm({
                message: this.translate('removeRelatedAttributeGroupCascade', 'messages', 'ProductAttributeValue'),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('removing');
                $.ajax({
                    url: `${this.scope}/action/unlinkAttributeGroupHierarchy`,
                    data: JSON.stringify({
                        attributeGroupId: id,
                        productId: this.model.id
                    }),
                    type: 'DELETE',
                    contentType: 'application/json',
                    success: function () {
                        this.notify('Removed', 'success');
                        this.model.trigger('after:unrelate', this.link, this.defs);
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

                if (model.has('valueTranslateAutomatically')) {
                    modelData['valueTranslateAutomatically'] = model.get('valueTranslateAutomatically');
                }

                if (model.has('valueTranslated')) {
                    modelData['valueTranslated'] = model.get('valueTranslated');
                }

                data[model.id] = Espo.Utils.cloneDeep(modelData);
            });
            return data;
        },

        panelFetch() {
            let data = false;
            this.groups.forEach(group => {
                const groupView = this.getView(group.key);
                if (groupView) {
                    (groupView.rowList || []).forEach(id => {
                        const row = groupView.getView(id);
                        const value = row.getView('valueField');
                        if (value.mode === 'edit') {
                            const fetchedData = value.fetch();
                            const initialData = this.initialAttributes[id];
                            value.model.set(fetchedData);

                            if (!this.equalityValueCheck(fetchedData, initialData)) {
                                fetchedData['_prev'] = initialData;
                                data = _.extend(data || {}, {[id]: fetchedData});
                            }
                        }
                    });
                }
            });
            return data;
        },

        equalityValueCheck(fetchedData, initialData) {
            if (typeof fetchedData.valueId !== 'undefined') {
                return _.isEqual(fetchedData.valueId, initialData.valueId);
            }

            if (typeof fetchedData.valueCurrency !== 'undefined') {
                fetchedData.data = {currency: fetchedData.valueCurrency};
                return _.isEqual(fetchedData.valueCurrency, initialData.valueCurrency) && _.isEqual(fetchedData.value, initialData.value);
            }

            if (typeof fetchedData.valueUnitId !== 'undefined') {
                return _.isEqual(fetchedData.valueUnitId, initialData.valueUnitId) && _.isEqual(fetchedData.value, initialData.value);
            }

            if (typeof fetchedData.valueFrom !== 'undefined') {
                return _.isEqual(fetchedData.valueFrom, initialData.valueFrom);
            }

            if (typeof fetchedData.valueTo !== 'undefined') {
                return _.isEqual(fetchedData.valueTo, initialData.valueTo);
            }

            if (typeof fetchedData.valueTranslateAutomatically !== 'undefined') {
                if (_.isEqual(fetchedData.valueTranslateAutomatically, initialData.valueTranslateAutomatically) === false) {
                    return false
                }
            }

            if (typeof fetchedData.valueTranslated !== 'undefined') {
                if (_.isEqual(fetchedData.valueTranslated, initialData.valueTranslated) === false) {
                    return false
                }
            }

            return _.isEqual(fetchedData.value, initialData.value);
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
                if (groupView) {
                    (groupView.rowList || []).forEach(id => {
                        const row = groupView.getView(id);
                        const value = row.getView('valueField');

                        if (value.mode === 'edit' && !value.disabled && !value.readOnly) {
                            notValid = value.validate() || notValid;
                        }
                    });
                }
            });
            return notValid;
        },

        hasCompleteness() {
            return this.getMetadata().get(['scopes', 'Product', 'hasCompleteness'])
                && this.getMetadata().get(['app', 'additionalEntityParams', 'hasCompleteness']);
        },

        actionRemoveRelatedHierarchically: function (data) {
            let id = data.id;
            this.confirm({
                message: this.translate('removeRecordConfirmationHierarchically', 'messages'),
                confirmText: this.translate('Remove')
            }, () => {
                let model = this.collection.get(id);
                this.notify('Removing...');
                $.ajax({
                    url: `ProductAttributeValue/${id}`,
                    type: 'DELETE',
                    data: JSON.stringify({
                        id: id,
                        hierarchically: true
                    }),
                    contentType: 'application/json',
                    success: () => {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link, this.defs);
                    },
                    error: () => {
                        this.collection.push(model);
                    },
                });
            });
        },

    })
);
