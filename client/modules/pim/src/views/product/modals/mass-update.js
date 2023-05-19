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

Espo.define('pim:views/product/modals/mass-update', 'views/modals/mass-update',
    Dep => Dep.extend({

        template: 'pim:product/modals/mass-update',

        events: _.extend({
            'click [data-action="select-attribute"]': function (e) {
                this.openAttributeListModal();
            },
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.attributeList = [];
        },

        data() {
            return _.extend({
                isAllowedMassUpdateAttributeValue: this.getAcl().check('ProductAttributeValue', 'edit')
            }, Dep.prototype.data.call(this));
        },

        reset() {
            Dep.prototype.reset.call(this);

            this.attributeList.forEach(function (field) {
                this.clearView(field);
                this.$el.find('.cell[data-name="' + field.name + '"]').remove();
            }, this);

            this.attributeList = [];
        },

        openAttributeListModal() {
            this.notify('Loading...');

            this.createView('modal', 'pim:views/product/modals/attributes-for-mass-update', {
                scope: 'ProductAttributeValue',
                layoutName: 'detailSmallForMassUpdate'
            }, function (view) {
                view.render();
                view.notify(false);

                view.listenTo(view, 'add-attribute', (model) => {
                    this.enableButton('update');

                    this.$el.find('[data-action="reset"]').removeClass('hidden');

                    this.createAttributeFieldView(model);

                    view.close();
                });
            }.bind(this));
        },

        createAttributeFieldView(model) {
            let name = model.get('attributeId'),
                language = model.get('language') || 'main';
            if (model.get('channelId')) {
                name += '_' + model.get('channelId');
            }
            name += '_' + language;

            let data = {
                name: name,
                attributeId: model.get('attributeId'),
                attributeType: model.get('attributeType'),
                scope: model.get('scope') || 'Global',
                channelId: model.get('channelId') || null,
                channelName: model.get('channelName') || null,
                language: language
            };
            let exist = this.attributeList.find(item => {
                if (item.attributeId === data.attributeId
                    && item.scope === data.scope
                    && item.channelId === data.channelId
                    && item.language === data.language) {
                    return item;
                }

                return false;
            });

            if (exist) {
                return;
            }

            this.attributeList.push(data);

            this.notify('Loading...');

            this.clearView(name);

            let html = '<div class="cell form-group col-sm-6" data-name="' + name + '"><label class="control-label">' + model.get('attributeName') + '</label><div class="field" data-name="' + name + '" /></div>';
            this.$el.find('.fields-container').append(html);

            let type = model.get('attributeType') || 'base';
            let viewName = this.getViewFieldType(type);

            let options = {
                name: name,
                model: model,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                mode: 'edit',
                params: {}
            };

            if (['int', 'float', 'rangeInt', 'rangeFloat', 'extensibleEnum', 'extensibleMultiEnum'].includes(type)) {
                this.ajaxGetRequest(`Attribute/${model.get('attributeId')}`, null, {async: false}).success(attr => {
                    if (attr.measureId) {
                        options.params.measureId = attr.measureId;
                        if (['int', 'float'].includes(type)) {
                            viewName = "views/fields/unit-" + type;
                        }
                    }
                    if (attr.extensibleEnumId) {
                        options.params.extensibleEnumId = attr.extensibleEnumId;
                    }
                });
            }

            this.createView(name, viewName, options, view => {
                view.listenTo(view, 'after:render', () => {
                    let name = data.channelName ? data.channelName : 'Global';
                    name += ', ' + this.getLanguage().translateOption(data.language, 'language', 'ProductAttributeValue');

                    view.$el.append('<div class="text-muted small">' + name + '</div>');
                });

                view.render();

                this.notify(false);
            });
        },

        getViewFieldType(type) {
            return this.getFieldManager().getViewName(type);
        },

        prepareData() {
            let result = Dep.prototype.prepareData.call(this);

            if (this.attributeList.length) {
                result.panelsData = {
                    productAttributeValues: []
                };

                this.attributeList.forEach(function (item) {
                    let view = this.getView(item.name),
                        name = view.name,
                        data = view.fetch();

                    let attribute = {
                        attributeId: item.attributeId,
                        scope: item.scope,
                        language: item.language
                    };

                    if (item.attributeType === 'asset') {
                        attribute.valueId = data[name + 'Id'];
                    } else {
                        attribute.value = data[name];
                    }

                    if (item.scope === 'Channel') {
                        attribute.channelId = item.channelId;
                        attribute.channelName = item.channelName;
                    }

                    if (data[name + 'UnitId']) {
                        attribute['valueUnitId'] = data[name + 'UnitId'];
                    }

                    if (view.fieldType === 'currency') {
                        attribute['valueCurrency'] = data[name + 'Currency'];
                    }

                    result.panelsData.productAttributeValues.push(attribute);
                }.bind(this));
            }

            return result;
        },

        isValid() {
            let result = Dep.prototype.isValid.call(this);

            this.attributeList.forEach(function (field) {
                var view = this.getView(field.name);
                result = view.validate() || result;
            }.bind(this));

            return result;
        }
    })
);
