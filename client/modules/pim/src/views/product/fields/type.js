

Espo.define('pim:views/product/fields/type', 'views/fields/enum',
    Dep => Dep.extend({

        data() {
            return _.extend({
                optionList: this.model.options || []
            }, Dep.prototype.data.call(this));
        },

        setupOptions() {
            var productType = Espo.Utils.clone(this.getMetadata().get('pim.productType'));
            var typeName = {};
            this.params.options = [];
            for (var type in productType) {
                this.params.options.push(type)
                typeName[type] = productType[type].name;
            }

            this.translatedOptions = Espo.Utils.clone(this.getLanguage().translate('type', 'options', 'Product') || {});
            // Add default name if not exist translate
            if(typeof this.translatedOptions !== 'object') {
                this.translatedOptions = typeName;
            }
        }

    })
);
