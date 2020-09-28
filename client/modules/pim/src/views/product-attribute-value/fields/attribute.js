

Espo.define('pim:views/product-attribute-value/fields/attribute', 'views/fields/link',
    Dep => Dep.extend({

        createDisabled: true,

        setup() {
            this.mandatorySelectAttributeList = ['type', 'typeValue', 'isMultilang'];
            let inputLanguageList = this.getConfig().get('inputLanguageList') || [];
            if (this.getConfig().get('isMultilangActive') && inputLanguageList.length) {
                this.typeValueFields = inputLanguageList.map(lang => {
                    return lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), 'typeValue');
                });
                this.mandatorySelectAttributeList = this.mandatorySelectAttributeList.concat(this.typeValueFields);
            }

            Dep.prototype.setup.call(this);
        },

        select(model) {
            this.setAttributeFieldsToModel(model);

            Dep.prototype.select.call(this, model);
        },

        setAttributeFieldsToModel(model) {
            let attributes = {
                attributeType: model.get('type'),
                typeValue: model.get('typeValue'),
                attributeIsMultilang: model.get('isMultilang')
            };
            (this.typeValueFields || []).forEach(item => attributes[item] = model.get(item));
            this.model.set(attributes);
        },

        clearLink() {
            this.unsetAttributeFieldsInModel();

            Dep.prototype.clearLink.call(this);
        },

        unsetAttributeFieldsInModel() {
            ['attributeType', 'typeValue', 'attributeIsMultilang', ...(this.typeValueFields || [])]
                .forEach(field => this.model.unset(field));
        }

    })
);

