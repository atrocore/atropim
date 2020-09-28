

Espo.define('pim:views/product-attribute-value/record/edit', ['pim:views/product-attribute-value/record/detail', 'views/record/edit'],
    (Detail, Dep) => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            Detail.prototype.handleValueModelDefsUpdating.call(this);
        },

        updateModelDefs() {
            Detail.prototype.updateModelDefs.call(this);
        },

        changeFieldsReadOnlyStatus(fields, condition) {
            Detail.prototype.changeFieldsReadOnlyStatus.call(this, fields, condition);
        },

        fetch() {
            return Detail.prototype.fetch.call(this);
        },

        extendFieldData(view, data) {
            Detail.prototype.extendFieldData.call(this, view, data);
        }

    })
);

