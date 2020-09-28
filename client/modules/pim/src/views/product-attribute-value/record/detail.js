

Espo.define('pim:views/product-attribute-value/record/detail', 'views/record/detail',
    Dep => Dep.extend({

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
                            this.model.set({[value]: null}, { silent: true });
                        });
                    }

                    this.clearView('middle');
                    this.gridLayout = null;
                    this.createMiddleView(() => this.reRender());
                }
            });
        },

        updateModelDefs() {
            // readOnly
            this.changeFieldsReadOnlyStatus(['attribute', 'channels', 'product', 'scope'], !this.model.get('isCustom'));

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
                        view: type !== 'bool' ? this.getFieldManager().getViewName(type) : 'pim:views/fields/bool-required',
                        required: !!this.model.get('isRequired')
                    };

                    // for unit
                    if (type === 'unit') {
                        fieldDefs.measure = (typeValue || ['Length'])[0];
                    }

                    // for multi-language
                    if (isMultiLang) {
                        if (this.getConfig().get('isMultilangActive')) {
                            (this.getConfig().get('inputLanguageList') || []).forEach(lang => {
                                let field = lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), 'value');
                                this.model.defs.fields[field] = Espo.Utils.cloneDeep(fieldDefs);
                            });
                        }
                        fieldDefs.isMultilang = true;
                    }

                    // set field defs
                    this.model.defs.fields.value = fieldDefs;
                }
            }
        },

        changeFieldsReadOnlyStatus(fields, condition) {
            fields.forEach(field => this.model.defs.fields[field].readOnly = condition);
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

            if (view.type === 'image') {
                _.extend((data || {}), {value: (data || {}).valueId});
            }

            if (additionalData) {
                _.extend((data || {}), {data: additionalData});
            }
        }

    })
);

