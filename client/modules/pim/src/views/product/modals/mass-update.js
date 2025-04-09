/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/modals/mass-update', 'views/modals/mass-update',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList.push({
                name: 'selectAttribute',
                label: 'Select Attribute',
                className: 'btn-select-attribute'
            });

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

        selectAttribute() {
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

            let html = '<div class="cell form-group col-sm-6" data-name="' + name + '"><div class="pull-right inline-actions"></div><label class="control-label">' + model.get('attributeName') + '</label><div class="field" data-name="' + name + '" /></div>';
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

                if(attr.isMultilang){
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

                    view.render(() => {
                        this.initRemoveField(view);
                        this.enableButton('update');
                    });

                    this.notify(false);
                });
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
                        name = view.originalName || view.name,
                        data = view.fetch();

                    let attribute = {
                        attributeId: item.attributeId,
                        channelId: item.channelId,
                        language: item.language
                    };

                    if (item.attributeType === 'file' || item.attributeType === 'link') {
                        attribute.valueId = data[name + 'Id'];
                    } else if(item.attributeType === 'linkMultiple'){
                        attribute.valueIds = data[name+ 'Ids']
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
