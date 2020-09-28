

Espo.define('pim:views/product-attribute-value/fields/attribute-with-required-sign', 'pim:views/product-attribute-value/fields/attribute',
    Dep => Dep.extend({

        data() {
            let data = Dep.prototype.data.call(this);

            if (this.model.get('isRequired')) {
                data.nameValue += ' *';
            }
            return data;
        }

    })
);

