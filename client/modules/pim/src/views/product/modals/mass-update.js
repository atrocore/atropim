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
 *
 * This software is not allowed to be used in Russia and Belarus.
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
            let name = model.get('attributeId');
            if (model.get('channelId')) {
                name += '_' + model.get('channelId');
            }

            let data = {
                name: name,
                attributeId: model.get('attributeId'),
                scope: model.get('scope') || 'Global',
                channelId: model.get('channelId') || null,
                channelName: model.get('channelName') || null
            };
            let exist = this.attributeList.find(item => {
                if (item.attributeId === data.attributeId && item.scope === data.scope && item.channelId === data.channelId) {
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

            let type = model.get('attributeType') || 'base',
                typeValue = model.get('typeValue'),
                options = {
                    name: name,
                    model: model,
                    el: this.getSelector() + ' .field[data-name="' + name + '"]',
                    mode: 'edit',
                    params: {
                        options: typeValue
                    }
            };

            if (type === 'unit') {
                options.params.measure = (typeValue || ['Length'])[0];
            }

            if (type === 'currency') {
                options.params.currency = typeValue || 'EUR';
            }

            this.createView(name, this.getViewFieldType(type), options, view => {
                view.listenTo(view, 'after:render', () => {
                    let name = 'Scope: ' + data.scope;
                    if (data.channelName) {
                        name += ', Channel: ' + data.channelName;
                    }

                    view.$el.append('<div class="text-muted small">' + name + '</div>');
                });

                view.render();

                this.notify(false);
            });
        },

        getViewFieldType(type) {
            if (type === 'enum') {
                return 'views/fields/enum';
            }

            if (type === 'multiEnum') {
                return 'views/fields/multi-enum';
            }

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
                        value: data[name]
                    };

                    if (item.scope === 'Channel') {
                        attribute.channelId = item.channelId;
                        attribute.channelName = item.channelName;
                    }

                    if (view.fieldType === 'unit') {
                        attribute['valueUnit'] = data[name + 'Unit'];
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
