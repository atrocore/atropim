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

Espo.define('pim:views/classification-attribute/fields/default-container', 'views/fields/base',
    (Dep) => Dep.extend({

        listTemplate: 'pim:classification-attribute/fields/default-container/base',

        detailTemplate: 'pim:classification-attribute/fields/default-container/base',

        editTemplate: 'pim:classification-attribute/fields/default-container/base',

        fieldActions: false,

        setup() {
            this.name = this.options.name || this.defs.name;
            let attributeType = this.model.attributes.attribute_type;
            console.log(this.model.get('default'));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.attributes.attribute_type) {
                let attributeType = this.model.attributes.attribute_type;

                let fieldView = this.getFieldManager().getViewName(attributeType);

                let params = {
                    required: !!this.model.attributes.attribute_isRequired,
                    readOnly: false,
                };

                if (this.getMetadata().get(['attributes', attributeType, 'isValueReadOnly'])) {
                    params.readOnly = true;
                }

                if (this.model.attributes.attribute_maxLength) {
                    params.maxLength = this.model.attributes.attribute_maxLength;
                    params.countBytesInsteadOfCharacters = this.model.attributes.attribute_countBytesInsteadOfCharacters;
                }

                if (this.model.attributes.attribute_useDisabledTextareaInViewMode) {
                    params.useDisabledTextareaInViewMode = this.model.attributes.attribute_useDisabledTextareaInViewMode;
                }

                if (this.model.attributes.attribute_amountOfDigitsAfterComma) {
                    params.amountOfDigitsAfterComma = this.model.attributes.attribute_amountOfDigitsAfterComma;
                }

                if (this.model.attributes.attribute_measureId) {
                    params.measureId = this.model.attributes.attribute_measureId;
                    if (['int', 'float'].includes(attributeType)) {
                        fieldView = "views/fields/unit-" + attributeType;
                    }
                }

                if (attributeType === 'extensibleEnum' || attributeType === 'extensibleMultiEnum') {
                    params.prohibitedEmptyValue = !!this.model.attributes.attribute_prohibitedEmptyValue;
                    params.extensibleEnumId = this.model.attributes.attribute_extensibleEnumId;
                }

                let options = {
                    el: `${this.options.el} > .field[data-name="defaultField"]`,
                    name: this.name,
                    model: this.model,
                    collection: this.model.collection || null,
                    params: params,
                    mode: this.mode,
                    inlineEditDisabled: true
                };

                this.createView('defaultField', fieldView, options, view => {
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
            let view = this.getView('defaultField');
            if (view) {
                _.extend(data, view.fetch());
                this.extendValueData(view, data);
            }
            return data;
        },

        extendValueData(view, data) {
            data = data || {};
            if (['asset', 'image'].includes(view.type)) {
                _.extend(data, {
                    [this.name]: data[`${this.name}Id`]
                });
            }
        },

        validate() {
            let validate = false;
            let view = this.getView('defaultField');
            if (view) {
                validate = view.validate();
            }
            return validate;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            let defaultField = this.getView('defaultField');
            if (defaultField) {
                defaultField.setMode(mode);
            }
        }
    })
);