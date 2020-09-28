

Espo.define('pim:views/dashlets/fields/colored-varchar-with-url', 'pim:views/dashlets/fields/varchar-with-url',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/colored-varchar-with-url/list',

        data() {
            let name = this.model.get(this.name);
            let fieldName = this.options.defs.params.filterField;
            let backgroundcolors = this.getMetadata().get(['entityDefs', 'Product', 'fields', fieldName, 'optionColors']) || {};
            return _.extend({
                backgroundColor: backgroundcolors[name],
                color: this.getFontColor(backgroundcolors[name])
            }, Dep.prototype.data.call(this));
        },

        getFontColor(backgroundColor) {
            if (backgroundColor) {
                let color;
                let r = parseInt(backgroundColor.substr(0, 2), 16);
                let g = parseInt(backgroundColor.substr(2, 2), 16);
                let b = parseInt(backgroundColor.substr(4, 2), 16);
                let l = 1 - ( 0.299 * r + 0.587 * g + 0.114 * b) / 255;
                l < 0.5 ? color = '000' : color = 'fff';
                return color;
            }
        }

    })
);

