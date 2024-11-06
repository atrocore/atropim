/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
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
            }
        },

        actionSelectRelated(selectObj) {

        },

        createProductAttributeValue(selectObj) {
            const data = {
                productId: this.model.get('id')
            }
            if (Array.isArray(selectObj)) {
                data.ids = selectObj.map(o => o.id)
            } else {
                data.where = selectObj.where
            }

            $.ajax({
                url: `ProductAttributeValue/action/selectAttribute`,
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                success: (resp) => {
                    this.notify(resp.message, 'success');
                    this.collection.fetch();
                    if(this.mode !== 'edit'){
                        this.model.trigger('after:unrelate', this.link, this.defs);
                    }
                }
            });
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

                    const boolFilterList = this.getSelectBoolFilterList() || [];
                    const data = {
                        productId: this.model.get('id'),
                        attributeWhere: [
                            {
                                type: 'bool',
                                value: boolFilterList,
                                data: this.getSelectBoolFilterData(boolFilterList)
                            }]
                    }
                    if (Array.isArray(selectObj)) {
                        data.ids = selectObj.map(o => o.id)
                    } else {
                        data.where = selectObj.where
                    }

                    $.ajax({
                        url: `ProductAttributeValue/action/selectAttributeGroup`,
                        type: 'POST',
                        data: JSON.stringify(data),
                        contentType: 'application/json',
                        success: (resp) => {
                            this.notify(resp.message, 'success');
                            this.collection.fetch();
                            if(this.mode !== 'edit'){
                                this.model.trigger('after:unrelate', this.link, this.defs);
                            }
                        }
                    });
                });
            });
        },

        fetchCollectionGroups(callback) {
            this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', {
                tabId: this.defs.tabId,
                productId: this.model.get('id'),
                fieldFilter: this.getStorage().get('fieldFilter', 'OverviewFilter') || ['allValues'],
                languageFilter: this.getStorage().get('languageFilter', 'OverviewFilter') || ['allLanguages'],
                scopeFilter: this.getStorage().get('scopeFilter', 'OverviewFilter') || ['linkedChannels']
            }).then(data => {
                this.groups = data;
                callback();
            });
        },

        initGroupCollection(group, groupCollection, callback) {
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

            group.collection.forEach(item => {
                item.tabId = this.defs.tabId;

                this.getModelFactory().create('ProductAttributeValue', model => {
                    model.set(item);
                    groupCollection.add(model);
                });
            });

            groupCollection.forEach(item => {
                if (this.collection.get(item.get('id'))) {
                    this.collection.remove(item.get('id'));
                }
                this.collection.add(item);
            });

            callback();
        },

        panelFetch() {
            let data = false;

            if (this.checkAclAction('edit')) {
                this.groups.forEach(group => {
                    const groupView = this.getView(group.key);
                    if (groupView) {
                        (groupView.rowList || []).forEach(id => {
                            const row = groupView.getView(id);
                            if (!row) return;
                            let fetchedData = {}
                            const initialData = this.initialAttributes[id];
                            this.editableFields.forEach(field => {
                                const value = row.getView(field + 'Field');
                                if (value && value.mode === 'edit') {
                                    fetchedData = _.extend(fetchedData, value.fetch());
                                }
                            });
                            row.model.set(fetchedData);
                            if (!this.equalityValueCheck(fetchedData, initialData)) {
                                fetchedData['_prev'] = initialData;
                                data = _.extend(data || {}, {[id]: fetchedData});
                            }
                        });
                    }
                });
            }
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

                this.editableFields.forEach(field => modelData[field] = model.get(field))

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

        isEmptyValue(value) {
            return (
                value === null ||
                value === undefined ||
                value === '' ||
                (Array.isArray(value) && value.length === 0) ||
                (typeof value === 'object' && Object.keys(value).length === 0)
            );
        },

        equalityValueCheck(fetchedData, initialData) {
            let result = true;

            this.editableFields.forEach(field => {
                if (field === 'value') return;
                if (this.isEmptyValue(fetchedData.value) && this.isEmptyValue(initialData.value)) {
                    result = result && true;
                    return;
                }
                result = result && _.isEqual(fetchedData[field], initialData[field])
            })

            if (!result) {
                return false;
            }

            if (typeof fetchedData.valueId !== 'undefined') {
                return _.isEqual(fetchedData.valueId, initialData.valueId);
            }

            if (typeof fetchedData.valueIds !== 'undefined') {
                return _.isEqual((fetchedData.valueIds || []).sort(), (initialData.valueIds || []).sort());
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

            if (this.isEmptyValue(fetchedData.value) && this.isEmptyValue(initialData.value)) {
                return true;
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
            Dep.prototype.afterGroupRender.call(this)
            this.initialAttributes = this.getInitialAttributes();
        },
        setEditMode() {
            if (!this.checkAclAction('edit')) {
                return;
            }

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
                        $(groupView.options.el +' div.list-row-buttons').hide()
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
        checkAclAction(action) {
            if (this.defs.tabId) {
                return this.getAcl().check('AttributeTab', 'edit') && this.getAcl().check('Attribute', action);
            }

            return this.getAcl().check('Attribute', action);
        }
    })
);
