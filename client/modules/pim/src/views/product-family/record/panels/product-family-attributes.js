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

Espo.define('pim:views/product-family/record/panels/product-family-attributes', ['views/record/panels/relationship', 'views/record/panels/bottom', 'search-manager'],
    (Dep, BottomPanel, SearchManager) => Dep.extend({

        template: 'pim:product-family/record/panels/product-family-attributes',

        groupKey: 'attributeGroupId',

        groupLabel: 'attributeGroupName',

        groupScope: 'AttributeGroup',

        noGroup: {
            key: 'no_group',
            label: 'No Group'
        },

        boolFilterData: {
            notLinkedProductFamilyAttributes() {
                return {
                    productFamilyId: this.model.id,
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

            if (this.defs.create && this.getAcl().check('ProductFamilyAttribute', 'create')) {
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

            if (this.defs.select && this.getAcl().check('ProductFamilyAttribute', 'create')) {
                var data = {link: this.link};
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }
                data.boolFilterListCallback = 'getSelectBoolFilterList';
                data.boolFilterDataCallback = 'getSelectBoolFilterData';
                data.afterSelectCallback = 'relateAttributes';
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

                this.listenTo(this.model, 'update-all after:relate after:unrelate', () => {
                    this.actionRefresh();
                });

                this.fetchCollectionGroups(() => this.wait(false));
            }, this);

            this.setupFilterActions();
        },

        relateAttributes(selectObj) {
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

        actionUnlinkRelatedAttribute(data) {
            var id = data.id;

            this.confirm({
                message: this.translate('unlinkRelatedAttribute', 'messages', 'ProductFamilyAttribute'),
                confirmText: this.translate('Unlink')
            }, function () {
                var model = this.collection.get(id);
                this.notify('Unlinking...');
                $.ajax({
                    url: this.collection.url,
                    type: 'DELETE',
                    data: JSON.stringify({
                        id: id
                    }),
                    contentType: 'application/json',
                    success: function () {
                        this.notify('Unlinked', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate');
                    }.bind(this),
                    error: function () {
                        this.notify('Error occurred', 'error');
                    }.bind(this),
                });
            }, this);
        },

        actionRemoveRelatedAttribute(data) {
            var id = data.id;

            this.confirm({
                message: this.translate('cascadeUnlinkRelatedAttribute', 'messages', 'ProductFamilyAttribute'),
                confirmText: this.translate('Remove')
            }, function () {
                var model = this.collection.get(id);
                this.notify('Removing...');
                model.destroy({
                    success: function () {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate');
                    }.bind(this),
                });
            }, this);
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
                boolFilterList: ['withNotLinkedAttributesToProductFamily'],
                boolFilterData: {withNotLinkedAttributesToProductFamily: this.model.id},
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
                        this.relateAttributes(models);
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
        },

        fetchCollectionGroups(callback) {
            this.getHelper().layoutManager.get(this.scope, this.layoutName, layout => {
                let list = [];
                layout.forEach(item => {
                    if (item.name) {
                        let field = item.name;
                        let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                        if (fieldType) {
                            this.getFieldManager().getAttributeList(fieldType, field).forEach(attribute => {
                                list.push(attribute);
                            });
                        }
                    }
                });
                this.collection.data.select = list.join(',');
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
                            let orderArray = [];
                            let noGroup;
                            this.groups.forEach(item => {
                                if (item.key === 'no_group') {
                                    item.sortOrder = 0;
                                    noGroup = item;
                                } else {
                                    this.attributeGroupCollection.forEach(model => {
                                        if (model.id === item.key) {
                                            item.sortOrder = model.get('sortOrder');
                                        }
                                    });
                                }
                                orderArray.push(item.sortOrder);
                            });
                            if (noGroup) {
                                noGroup.sortOrder = Math.max(...orderArray) + 1;
                            }
                            this.groups.sort(function (a, b) {
                                return a.sortOrder - b.sortOrder;
                            });

                            if (callback) {
                                callback();
                            }
                        });
                    });
                });
            });
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
                // prepare key
                let key = model.get(this.groupKey);
                if (key === null || typeof key === 'undefined') {
                    key = this.noGroup.key;
                }

                // prepare label
                let label = model.get(this.groupLabel);
                if (label === null || typeof label === 'undefined') {
                    label = this.translate(this.noGroup.label, 'labels', 'Global');
                }

                // prepare is inherited param
                let isInherited = model.get('isInherited');
                if (isInherited === null || typeof isInherited === 'undefined') {
                    isInherited = false;
                }

                let group = groups.find(item => item.key === key);
                if (group) {
                    group.rowList.push(model.id);
                    group.rowList.sort((a, b) => this.collection.get(a).get('sortOrder') - this.collection.get(b).get('sortOrder'));
                    group.editable = (!group.editable) ? !isInherited : group.editable;
                } else {
                    groups.push({
                        key: key,
                        id: key !== this.noGroup.key ? key : null,
                        label: label,
                        rowList: [model.id],
                        editable: !isInherited
                    });
                }
            });

            return groups;
        },

        buildGroups() {
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, collection => {
                    let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';
                    this.getHelper().layoutManager.get(this.scope, this.layoutName, layout => {
                        this.createView(group.key, viewName, {
                            collection: this.prepareGroupCollection(group, collection),
                            layoutName: this.layoutName,
                            listLayout: this.prepareListLayout(layout),
                            checkboxes: false,
                            rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                            buttonsDisabled: true,
                            el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                            showMore: false
                        }, view => {
                            view.render();
                        });
                    });
                });
            });
        },

        prepareGroupCollection(group, collection) {
            group.rowList.forEach(id => {
                collection.add(this.collection.get(id));
            });

            collection.url = `ProductFamily/${this.model.id}/productFamilyAttributes`;
            collection.where = [
                {
                    type: 'bool',
                    value: ['linkedWithAttributeGroup'],
                    data: {
                        linkedWithAttributeGroup: {
                            productFamilyId: this.model.id,
                            attributeGroupId: group.key !== 'no_group' ? group.key : null
                        }
                    }
                }
            ];
            collection.data.select = 'attributeId,attributeName,value,valueEnUs,valueDeDe,scope,channelsIds,channelsNames';

            this.listenTo(collection, 'sync', () => {
                collection.models.sort((a, b) => a.get('sortOrder') - b.get('sortOrder'));
            });

            return collection;
        },

        prepareListLayout(layout) {
            layout.forEach((v, k) => {
                layout[k]['notSortable'] = true;
            });

            return layout;
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
            this.fetchCollectionGroups(() => this.reRender());
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

            // prepare ids
            let ids = [];
            group.rowList.forEach(id => {
                if (!this.collection.get(id).get('isInherited')) {
                    ids.push(id);
                }
            });
            if (!ids) {
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
                        foreignIds: ids
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

    })
);