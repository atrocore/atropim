/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/product-attribute-values', ['pim:views/record/panels/records-in-groups'],
    (Dep) => Dep.extend({

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
        groupScope: 'AttributeGroup',
        groupKey: 'attributeGroupId',
        selectScope: 'Attribute',
        groupLabel: 'attributeGroupName',
        disableDeleteAll: true,
        groupsWithoutId: ['no_group'],
        hierarchyEnabled: true,
        afterSelectCallback: 'createProductAttributeValue',
        selectLabel: 'selectAttributeGroup',

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

        actionSelectRelated(selectObj) {
            console.log(selectObj)
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
        actionSelectGroup() {
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

        fetchCollectionGroups(callback) {
            this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', {
                tabId: this.defs.tabId,
                productId: this.model.get('id')
            }).then(data => {
                this.groups = data;
                callback();
            });
        },
        initGroupCollection(group, groupCollection) {
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

        panelFetch() {
            let data = false;
            this.groups.forEach(group => {
                const groupView = this.getView(group.key);
                if (groupView) {
                    (groupView.rowList || []).forEach(id => {
                        const row = groupView.getView(id);
                        const value = row.getView('valueField');
                        if (value && value.mode === 'edit') {
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

        unlinkGroup(data, hierarchically = false) {
            let id = data.id;
            if (!id) {
                return;
            }

            let group = this.groups.find(group => group.id === id);
            if (!group || !group.rowList) {
                return;
            }

            this.confirm({
                message: this.translate(hierarchically ? 'removeRelatedAttributeGroupCascade' : 'removeRelatedAttributeGroup', 'messages', 'ProductAttributeValue'),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('removing');
                const data = {
                    attributeGroupId: id,
                    productId: this.model.id
                }
                if (hierarchically) data.hierarchically = true
                $.ajax({
                    url: `${this.scope}/action/unlinkAttributeGroup`,
                    data: JSON.stringify(data),
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

        unlinkGroupHierarchy(data) {
            this.unlinkGroup(data, true)
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

                if (model.has('valueApproved')) {
                    modelData['valueApproved'] = model.get('valueApproved');
                }

                if (model.has('valueNotTranslateFrom')) {
                    modelData['valueNotTranslateFrom'] = model.get('valueNotTranslateFrom');
                }

                if (model.has('valueNotTranslateTo')) {
                    modelData['valueNotTranslateTo'] = model.get('valueNotTranslateTo');
                }

                data[model.id] = Espo.Utils.cloneDeep(modelData);
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

            if (typeof fetchedData.valueApproved !== 'undefined') {
                if (_.isEqual(fetchedData.valueApproved, initialData.valueApproved) === false) {
                    return false
                }
            }

            if (typeof fetchedData.valueNotTranslateFrom !== 'undefined') {
                if (_.isEqual(fetchedData.valueNotTranslateFrom, initialData.valueNotTranslateFrom) === false) {
                    return false
                }
            }

            if (typeof fetchedData.valueNotTranslateTo !== 'undefined') {
                if (_.isEqual(fetchedData.valueNotTranslateTo, initialData.valueNotTranslateTo) === false) {
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

        afterGroupRender() {
            this.initialAttributes = this.getInitialAttributes();
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
