/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/modals/mass-update', 'views/modal',
    Dep => Dep.extend({

        cssName: 'mass-update',

        header: false,

        template: 'pim:product/modals/mass-update',

        fullHeight: true,

        data: function () {
            return {
                scope: this.scope,
                fields: this.fields,
                isAllowedMassUpdateAttributeValue: this.getAcl().check('ProductAttributeValue', 'edit')
            };
        },

        events: {
            'click button[data-action="update"]': function () {
                this.update();
            },
            'click a[data-action="add-field"]': function (e) {
                var field = $(e.currentTarget).data('name');
                this.addField(field);
            },
            'click button[data-action="reset"]': function (e) {
                this.reset();
            },
            'click [data-action="select-attribute"]': function (e) {
                this.openAttributeListModal();
            },
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'update',
                    label: 'Update',
                    style: 'danger',
                    disabled: true
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.options.scope;
            this.ids = this.options.ids;
            this.where = this.getWhere();
            this.selectData = this.options.selectData;
            this.byWhere = this.options.byWhere;

            this.header = this.translate(this.scope, 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update');

            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;
                this.model.set({ids: this.ids});
                let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope) || [];

                this.fields = [];
                $.each((this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}), (field, row) => {
                    if (~forbiddenFieldList.indexOf(field)) return;
                    if (row.layoutMassUpdateDisabled) return;
                    if (row.massUpdateDisabled) return;
                    this.fields.push(field);
                });
                this.fields.sort()
            }.bind(this));

            this.fieldList = [];
            this.attributeList = [];
        },

        addField: function (name) {
            this.enableButton('update');

            this.$el.find('[data-action="reset"]').removeClass('hidden');

            this.$el.find('ul.filter-list li[data-name="' + name + '"]').addClass('hidden');

            if (this.$el.find('ul.filter-list li:not(.hidden)').size() == 0) {
                this.$el.find('button.select-field').addClass('disabled').attr('disabled', 'disabled');
            }

            this.notify('Loading...');
            var label = this.translate(name, 'fields', this.scope);
            var html = '<div class="cell form-group col-sm-6" data-name="' + name + '"><label class="control-label">' + label + '</label><div class="field" data-name="' + name + '" /></div>';
            this.$el.find('.fields-container').append(html);

            var type = this.model.getFieldType(name);

            var viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.model,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    isMassUpdate: true
                },
                mode: 'edit'
            }, function (view) {
                this.fieldList.push(name);
                view.render();
                view.notify(false);
            }.bind(this));
        },

        actionUpdate: function () {
            this.disableButton('update');

            var self = this;

            var attributes = this.prepareData();

            this.model.set(attributes);

            if (!this.isValid()) {
                self.notify('Saving...');
                $.ajax({
                    url: this.scope + '/action/massUpdate',
                    type: 'PUT',
                    data: JSON.stringify({
                        attributes: attributes,
                        ids: self.ids || null,
                        where: (!self.ids || self.ids.length == 0) ? self.options.where : null,
                        selectData: (!self.ids || self.ids.length == 0) ? self.options.selectData : null,
                        byWhere: this.byWhere
                    }),
                    success: function (result) {
                        self.trigger('after:update', result);
                    },
                    error: function () {
                        self.notify('Error occurred', 'error');
                        self.enableButton('update');
                    }
                });
            } else {
                this.notify('Not valid', 'error');
                this.enableButton('update');
            }
        },

        reset: function () {
            this.fieldList.forEach(function (field) {
                this.clearView(field);
                this.$el.find('.cell[data-name="' + field + '"]').remove();
            }, this);

            this.fieldList = [];

            this.attributeList.forEach(function (field) {
                this.clearView(field);
                this.$el.find('.cell[data-name="' + field.name + '"]').remove();
            }, this);

            this.attributeList = [];

            this.model.clear();

            this.$el.find('[data-action="reset"]').addClass('hidden');

            this.$el.find('button.select-field').removeClass('disabled').removeAttr('disabled');
            this.$el.find('ul.filter-list').find('li').removeClass('hidden');

            this.disableButton('update');
        },

        isValid() {
            var notValid = false;
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                notValid = view.validate() || notValid;
            }.bind(this));

            this.attributeList.forEach(function (field) {
                var view = this.getView(field.name);
                notValid = view.validate() || notValid;
            }.bind(this));

            return notValid;
        },

        getWhere() {
            let where = this.options.where;
            let cleanWhere = (where) => {
                where.forEach(wherePart => {
                    if (['in', 'notIn'].includes(wherePart['type'])) {
                        if ('value' in wherePart && !(wherePart['value'] ?? []).length) {
                            delete wherePart['value']
                        }
                    }

                    if (['and', 'or'].includes(wherePart['type']) && Array.isArray(wherePart['value'] ?? [])) {
                        cleanWhere(wherePart['value'] ?? [])
                    }
                })
            };
            cleanWhere(where);
            return where;
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

            this.ajaxGetRequest(`Attribute/${model.get('attributeId')}`, null, {async: false}).success(attr => {
                let type = attr.type || 'base';
                let viewName = this.getViewFieldType(type);

                let options = {
                    name: name,
                    defs: {
                        isMassUpdate: true,
                    },
                    model: model.clone(),
                    el: this.getSelector() + ' .field[data-name="' + name + '"]',
                    mode: 'edit',
                    params: {}
                };

                if (attr.measureId) {
                    options.params.measureId = attr.measureId;
                    if (['int', 'float', 'varchar'].includes(type)) {
                        viewName = "views/fields/unit-" + type;
                    }
                }

                if (attr.extensibleEnumId) {
                    options.params.extensibleEnumId = attr.extensibleEnumId;
                }

                if (attr.isMultilang) {
                    options.params.multilangLocale = language
                }

                if (type === 'link' || type === 'linkMultiple') {
                    options.foreignScope = attr.entityType;
                    options.params.foreignName = attr.entityField;
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
            });
        },

        getViewFieldType(type) {
            return this.getFieldManager().getViewName(type);
        },

        prepareData() {
            var result = {};
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                _.extend(result, view.fetch());
            }.bind(this));

            if (this.attributeList.length) {
                result.panelsData = {
                    productAttributeValues: []
                };

                this.attributeList.forEach(function (item) {
                    let view = this.getView(item.name),
                        name = view.originalName || view.name,
                        data = view.fetch();

                    let attribute = {
                        attributeId: item.attributeId,
                        channelId: item.channelId,
                        language: item.language
                    };

                    if (item.attributeType === 'file' || item.attributeType === 'link') {
                        attribute.valueId = data[name + 'Id'];
                    } else if (item.attributeType === 'linkMultiple') {
                        attribute.valueIds = data[name + 'Ids']
                    } else {
                        attribute.value = data[name];
                    }

                    if (item.channelId && item.channelId !== '') {
                        attribute.channelName = item.channelName;
                    }

                    if (data[name + 'UnitId']) {
                        attribute['valueUnitId'] = data[name + 'UnitId'];
                    }

                    if (data[name + 'Translate']) {
                        attribute['valueTranslate'] = data[name + 'Translate']
                    }

                    result.panelsData.productAttributeValues.push(attribute);
                }.bind(this));
            }

            return result;
        },

    })
);
