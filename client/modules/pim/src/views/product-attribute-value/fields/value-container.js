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

Espo.define('pim:views/product-attribute-value/fields/value-container', 'views/fields/base',
    (Dep) => Dep.extend({

        listTemplate: 'pim:product-attribute-value/fields/base',

        detailTemplate: 'pim:product-attribute-value/fields/base',

        editTemplate: 'pim:product-attribute-value/fields/base',

        setup() {
            this.name = this.options.name || this.defs.name;
            if (this.model.attributes.attributeType === 'text') {
                console.log('in the setup, test doug');
                console.log(this);
            }

            this.listenTo(this.model, 'change:attributeId', () => {
                if (this.mode === 'detail' || this.mode === 'edit') {
                    this.clearValue();

                    if (this.model.get('attributeId')) {
                        this.fetchAttributeData();
                    }
                }
            });
        },

        clearValue() {
            this.model.set('value', undefined);
            this.model.set('valueCurrency', undefined);
            this.model.set('valueUnitId', undefined);
            this.model.set('valueAllUnits', undefined);
            this.model.set('valueId', undefined);
            this.model.set('valueName', undefined);
            this.model.set('valuePathsData', undefined);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('attributeType')) {
                let attributeType = this.model.get('attributeType');

                let fieldView = this.getFieldManager().getViewName(attributeType);

                let params = {
                    required: !!this.model.get('isRequired'),
                    readOnly: !!this.model.get('isValueReadOnly')
                };

                if (this.getMetadata().get(['attributes', this.model.get('attributeType'), 'isValueReadOnly'])) {
                    params.readOnly = true;
                }

                if (this.model.get('maxLength')) {
                    params.maxLength = this.model.get('maxLength');
                    params.countBytesInsteadOfCharacters = this.model.get('countBytesInsteadOfCharacters');
                }
                params.useDisabledTextareaInViewMode = this.model.get('useDisabledTextareaInViewMode');

                if (this.model.get('amountOfDigitsAfterComma')) {
                    params.amountOfDigitsAfterComma = this.model.get('amountOfDigitsAfterComma');
                }

                if (this.model.get('attributeMeasureId')) {
                    params.measureId = this.model.get('attributeMeasureId');
                    if (['int', 'float'].includes(attributeType)) {
                        fieldView = "views/fields/unit-" + attributeType;
                    }
                }

                if (attributeType === 'extensibleEnum' || attributeType === 'extensibleMultiEnum') {
                    params.prohibitedEmptyValue = !!this.model.get('prohibitedEmptyValue');
                    params.extensibleEnumId = this.model.get('attributeExtensibleEnumId');
                }

                var tagElement = '> .field[data-name="valueField"]';

                let options = {
                    el: `${this.options.el} ${tagElement}`,
                    name: this.name,
                    model: this.model,
                    collection: this.model.collection || null,
                    params: params,
                    mode: this.mode,
                    inlineEditDisabled: true
                };

                this.createView('valueField', fieldView, options, view => {
                    view.render();

                    this.listenTo(this.model, 'change:isRequired', () => {
                        if (this.model.get('isRequired')) {
                            view.setRequired();
                        } else {
                            view.setNotRequired();
                        }
                    });
                });

                if (this.mode === 'edit' && 'extensibleMultiEnum' === attributeType) {
                    this.$el.addClass('over-visible');
                }
            }
        },

        fetch() {
            let data = {};
            let view = this.getView('valueField');
            if (view) {
                _.extend(data, view.fetch());
                this.extendValueData(view, data);
            }
            return data;
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.useTextarea = this.checkIfWeuseTextArea();
            
            return data;
        },

        checkIfWeuseTextArea: function () {
            if (this.model.get('useDisabledTextareaInViewMode')) {
                const typeToChangeDisplaying = ['text', 'varchar', 'wysiwyg'];
                if (typeToChangeDisplaying.includes(this.model.attributes.attributeType)) {
                    return true;
                }
            }
            return false;
        },

        extendValueData(view, data) {
            data = data || {};
            if (['asset', 'image'].includes(view.type)) {
                _.extend(data, {
                    [this.name]: data[`${this.name}Id`]
                });
            }

            if (this.model.has('valueTranslateAutomatically')) {
                data['valueTranslateAutomatically'] = this.model.get('valueTranslateAutomatically');
                let $auto = this.$element.parent().find(`[data-parameter='auto']`);
                if ($auto.length > 0) {
                    data['valueTranslateAutomatically'] = $auto.is(":checked");
                }
            }

            if (this.model.has('valueTranslated')) {
                data['valueTranslated'] = this.model.get('valueTranslated');
                let $translated = this.$element.parent().find(`[data-parameter='translated']`);
                if ($translated.length > 0) {
                    data['valueTranslated'] = $translated.is(":checked");
                }
            }
        },

        validate() {
            let validate = false;
            let view = this.getView('valueField');
            if (view) {
                validate = view.validate();
            }
            return validate;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            let valueField = this.getView('valueField');
            if (valueField) {
                valueField.setMode(mode);
            }
        },

        fetchAttributeData() {
            this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                this.model.set('attributeType', attr.type);
                this.model.set('attributeExtensibleEnumId', attr.extensibleEnumId);
                this.model.set('maxLength', attr.maxLength);
                this.model.set('countBytesInsteadOfCharacters', attr.countBytesInsteadOfCharacters);
                this.model.set('useDisabledTextareaInViewMode', attr.useDisabledTextareaInViewMode);
                this.model.set('prohibitedEmptyValue', !!attr.prohibitedEmptyValue);
                this.reRender();
            });
        }
    })
);

