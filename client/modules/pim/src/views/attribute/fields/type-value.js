

Espo.define('pim:views/attribute/fields/type-value', 'views/fields/array',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.resetValue();
                this.setMode(this.mode);
                this.reRender();
            });
        },

        setMode: function (mode) {
            // prepare mode
            this.mode = mode;

            // prepare type
            let type = (this.model.get('type') === 'unit') ? 'enum' : 'array';

            // set template
            this.template = 'fields/' + Espo.Utils.camelCaseToHyphen(type) + '/' + this.mode;
        },

        data() {
            let data = Dep.prototype.data.call(this);

            data.name = this.name;
            data = this.modifyDataByType(data);

            return data;
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            data = this.modifyFetchByType(data);

            return data;
        },

        modifyFetchByType(data) {
            let fetchedData = data;
            if (this.model.get('type') === 'unit') {
                fetchedData = {};
                fetchedData[this.name] = [this.$el.find(`[name="${this.name}"]`).val()];
            }

            return fetchedData;
        },

        modifyDataByType(data) {
            data = Espo.Utils.cloneDeep(data);
            if (this.model.get('type') === 'unit') {
                let options = Object.keys(this.getConfig().get('unitsOfMeasure') || {});
                data.params.options = options;
                let translatedOptions = {};
                options.forEach(item => translatedOptions[item] = this.getLanguage().get('Global', 'measure', item));
                data.translatedOptions = translatedOptions;
                let value = this.model.get(this.name);
                if (
                    value !== null
                    &&
                    value !== ''
                    ||
                    value === '' && (value in (translatedOptions || {}) && (translatedOptions || {})[value] !== '')
                ) {
                    data.isNotEmpty = true;
                }
            }

            return data;
        },

        resetValue() {
            this.selectedComplex = {[this.name]: null};
            this.model.set(this.selectedComplex);
        }

    })
);