

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

Espo.define('pim:views/product/modals/add-channel-attribute', 'views/modals/edit',
    Dep => Dep.extend({

        inputLanguageListKeys: false,

        fullFormDisabled: true,

        sideDisabled: true,

        bottomDisabled: true,

        template: 'pim:product/modals/add-channel-attribute',

        setup() {
            this.buttonList = [];

            if ('saveDisabled' in this.options) {
                this.saveDisabled = this.options.saveDisabled;
            }

            if (!this.saveDisabled) {
                this.buttonList.push({
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                });
            }

            this.fullFormDisabled = this.options.fullFormDisabled || this.fullFormDisabled;

            this.layoutName = this.options.layoutName || this.layoutName;

            if (!this.fullFormDisabled) {
                this.buttonList.push({
                    name: 'fullForm',
                    label: 'Full Form'
                });
            }

            this.buttonList.push({
                name: 'cancel',
                label: 'Cancel'
            });

            this.scope = this.scope || this.options.scope;
            this.id = this.options.id;

            if (!this.id) {
                this.header = this.getLanguage().translate('Create ' + this.scope, 'labels', this.scope);
            } else {
                this.header = this.getLanguage().translate('Edit');
                this.header += ': ' + this.getLanguage().translate(this.scope, 'scopeNames');
            }

            if (!this.fullFormDisabled) {
                if (!this.id) {
                    this.header = '<a href="#' + this.scope + '/create" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                } else {
                    this.header = '<a href="#' + this.scope + '/edit/' + this.id+'" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                }
            }

            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header = iconHtml + this.header;

            this.sourceModel = this.model;

            this.waitForView('edit');
            this.getModelFactory().create('channelProductAttributeGrid', function (model) {
                this.ajaxGetRequest(`Markets/Product/${this.options.productId}/attributes`)
                    .then(response => {
                        let channel = this.options.channels.find(item => item.channelId === this.options.channelId);
                        let options = [];
                        let translateOptions = {};

                        let inputLanguageList = this.getConfig().get('inputLanguageList');
                        if (Array.isArray(inputLanguageList) && inputLanguageList.length) {
                            this.inputLanguageListKeys = inputLanguageList.map(lang => lang.split('_').reduce((prev, curr) => prev + Espo.utils.upperCaseFirst(curr.toLowerCase()), ''));
                        }

                        let existedAttributes = [];
                        (channel.attributes || []).forEach(attribute => {
                            if (!existedAttributes.includes(attribute.attributeId)) {
                                existedAttributes.push(attribute.attributeId);
                            }
                        });
                        response = (response || []).filter(attribute => !existedAttributes.includes(attribute.attributeId));

                        response.forEach(attribute => {
                            if (attribute.attributeId && !channel.attributes.find(item => item.attributeId === attribute.attributeId)) {
                                options.push(attribute.attributeId);
                                translateOptions[attribute.attributeId] = attribute.name;
                            }
                        });
                        if (options.length) {
                            let first = response.find(item => item.attributeId === options[0]);
                            this.getLanguage().data['channelProductAttributeGrid'] = {
                                fields: {
                                    attributeId: this.translate('Attribute', 'scopeNames'),
                                    value: this.translate('value', 'fields', 'ChannelProductAttributeValue')
                                },
                                options: {attributeId: translateOptions}
                            };

                            model.defs.fields = {
                                attributeId: {
                                    type: 'enum',
                                    options: options,
                                    required: true
                                },
                                value: {
                                    type: first.type,
                                    options: first.typeValue,
                                    measure: first.typeValue
                                }
                            };

                            let data = {
                                attributeId: first.attributeId,
                                value: first.value,
                                data: first.data
                            };

                            if (this.inputLanguageListKeys) {
                                this.inputLanguageListKeys.forEach(item => {
                                    data[`value${item}`] = first[`value${item}`];
                                    model.defs.fields.value[`options${item}`] = first[`typeValue${item}`];
                                });
                            }
                            model.id = first.productAttributeValueId;
                            model.set(data);

                            this.createRecordView(model);

                            this.listenTo(model, 'change:attributeId', model => {
                                if (model.changed.attributeId) {
                                    let current = response.find(item => item.attributeId === model.get('attributeId'));

                                    model.defs.fields.value = {
                                        type: current.type,
                                        options: current.typeValue,
                                        measure: current.typeValue
                                    };

                                    let data = {
                                        value: current.value,
                                        data: current.data
                                    };
                                    if (this.inputLanguageListKeys) {
                                        this.inputLanguageListKeys.forEach(item => {
                                            data[`value${item}`] = current[`value${item}`];
                                            model.defs.fields.value[`options${item}`] = current[`typeValue${item}`];
                                        });
                                    }
                                    model.id = current.productAttributeValueId;
                                    model.set(data);

                                    this.getView('edit').attributes = {};
                                    this.getView('edit').isChanged = false;

                                    this.createRecordView(model, view => view.render());
                                }
                            }, this);
                        } else {
                            this.createEmptyDataView();
                        }
                    });
            }.bind(this));
        },

        createRecordView(model, callback) {
            let detailLayout = [
                {
                    "label": "",
                    "rows": [
                        [
                            {
                                "name": "attributeId",
                            },
                            {
                                "name": "value",
                            }
                        ]
                    ]
                }
            ];
            let viewName =
                this.editViewName ||
                this.editView ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editSmall']) ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editQuick']) ||
                'views/record/edit-small';
            let options = {
                model: model,
                el: this.containerSelector + ' .edit-container',
                type: 'editSmall',
                layoutName: this.layoutName || 'detailSmall',
                detailLayout: detailLayout,
                columnCount: this.columnCount,
                buttonsPosition: false,
                sideDisabled: this.sideDisabled,
                bottomDisabled: this.bottomDisabled,
                isWide: true,
                exit: function () {}
            };
            this.handleRecordViewOptions(options);
            this.createView('edit', viewName, options, callback);
        },

        createEmptyDataView() {
            this.createView('edit', 'views/base', {
                template: 'pim:product/modals/empty-data'
            });
        },

        actionSave: function () {
            this.getModelFactory().create('ChannelProductAttributeValue', model => {
                let data = {
                    ...this.getView('edit').fetch(),
                    productAttributeId: this.getView('edit').model.id,
                    channelId: this.options.channelId
                };

                let additionalData = this.getAdditionalFieldData(this.getView('edit').getFieldView('value'), data);
                if (additionalData) {
                    data.data = additionalData;
                }

                ['ownerUser', 'assignedUser'].forEach(field => {
                    if (model.hasField(field)) {
                        data[`${field}Id`] = this.getUser().id;
                    }
                });

                model.set(data);
                model.save().then(() => {
                    this.notify('Saved', 'success');
                    this.trigger('after:save');
                    this.dialog.close();
                    this.getParentView().actionRefresh();
                });
            });
        },

        getAdditionalFieldData(view, data) {
            let additionalData = false;
            if (view.type === 'unit') {
                let actualFieldDefs = this.getMetadata().get(['fields', view.type, 'actualFields']) || [];
                let actualFieldValues = this.getFieldManager().getActualAttributes(view.type, view.name) || [];
                actualFieldDefs.forEach((field, i) => {
                    if (field) {
                        additionalData = additionalData || {};
                        additionalData[field] = data[actualFieldValues[i]];
                    }
                });
            }
            return additionalData;
        },

    })
);

