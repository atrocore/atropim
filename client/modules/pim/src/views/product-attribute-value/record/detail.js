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

Espo.define('pim:views/product-attribute-value/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        sideView: "pim:views/product-attribute-value/record/detail-side",

        setup() {
            Dep.prototype.setup.call(this);

            this.handleValueModelDefsUpdating();
        },

        handleValueModelDefsUpdating() {
            this.updateModelDefs();
            this.listenTo(this.model, 'change:attributeId', () => {
                this.updateModelDefs();
                if (this.model.get('attributeId')) {
                    const inputLanguageList = this.getConfig().get('inputLanguageList') || [];

                    if (this.getConfig().get('isMultilangActive') && inputLanguageList.length) {
                        const valuesKeysList = ['value', ...inputLanguageList.map(lang => {
                            return lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), 'value');
                        })];

                        valuesKeysList.forEach(value => {
                            this.model.set({[value]: null}, {silent: true});
                        });
                    }

                    this.clearView('middle');
                    this.gridLayout = null;
                    this.createMiddleView(
                        function (view) {
                            view.render();
                        }
                    );
                }
            });
        },

        updateModelDefs() {
            if (this.model.get('attributeId')) {
                // prepare data
                let type = this.model.get('attributeType');
                let isMultiLang = this.model.get('attributeIsMultilang');
                let typeValue = this.model.get('typeValue');

                if (type) {
                    // prepare field defs
                    let fieldDefs = {
                        type: type,
                        options: typeValue,
                        view: this.getFieldManager().getViewName(type),
                        prohibitedEmptyValue: !!this.model.get('prohibitedEmptyValue'),
                        required: !!this.model.get('isRequired')
                    };

                    // for unit
                    if (type === 'unit') {
                        fieldDefs.measure = (typeValue || ['Length'])[0];
                    }

                    // for currency
                    if (type === 'currency') {
                        fieldDefs.currency = typeValue || 'EUR';
                    }

                    if (type === 'enum') {
                        fieldDefs.view = 'views/fields/enum';
                    }

                    if (type === 'multiEnum') {
                        fieldDefs.view = 'views/fields/multi-enum';
                    }

                    // set field defs
                    this.model.defs.fields.value = fieldDefs;
                }
            }
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            let view = this.getFieldView('value');
            if (view) {
                this.extendFieldData(view, data);
            }
            return data;
        },

        extendFieldData(view, data) {
            let additionalData = false;

            if (view.type === 'unit' || view.type === 'currency') {
                let actualFieldDefs = this.getMetadata().get(['fields', view.type, 'actualFields']) || [];
                let actualFieldValues = this.getFieldManager().getActualAttributes(view.type, view.name) || [];
                actualFieldDefs.forEach((field, i) => {
                    if (field) {
                        additionalData = additionalData || {};
                        additionalData[field] = data[actualFieldValues[i]];
                    }
                });
            }

            if (view.type === 'image') {
                _.extend((data || {}), {value: (data || {}).valueId});
            }

            if (additionalData) {
                _.extend((data || {}), {data: additionalData});
            }
        }

    })
);

