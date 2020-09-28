

Espo.define('pim:views/product/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'pim:product/search/filter',

        setup: function () {
            let name = this.name = this.options.name;
            name = name.split('-')[0];
            this.clearedName = name;
            let type = this.model.getFieldType(name) || this.options.params.type;

            if (type) {
                let viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

                let params = {};
                if (this.options.params.isTypeValue) {
                    params = {
                        options: this.options.params.options,
                        translatedOptions: this.options.params.translatedOptions
                    }
                }
                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    name: name,
                    params: params,
                    searchParams: this.options.searchParams,
                });
            }
        },

        data: function () {
            return _.extend({
                label: this.options.params.isAttribute ? this.options.params.label : this.getLanguage().translate(this.name, 'fields', this.scope),
                clearedName: this.clearedName
            }, Dep.prototype.data.call(this));
        }
    });
});

