

Espo.define('pim:views/dashlets/fields/percent-varchar', 'views/fields/float',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/percent-varchar/list',

        getValueForDisplay() {
            return `${this.model.get(this.name)}%`;
        }

    })
);

