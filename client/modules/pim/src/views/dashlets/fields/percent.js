

Espo.define('pim:views/dashlets/fields/percent', 'views/fields/float',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/percent/list',

        getValueForDisplay() {
            let total = 0;
            this.model.collection.each(model => total += model.get('amount'));
            return (total ? this.formatNumber(Math.round(this.model.get('amount') / total * 10000) / 100) : 0) + '%';
        }

    })
);

