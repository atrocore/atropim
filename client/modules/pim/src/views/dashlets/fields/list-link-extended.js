

Espo.define('pim:views/dashlets/fields/list-link-extended', 'views/fields/base',
    Dep => Dep.extend({

        listLinkTemplate: 'pim:dashlets/fields/list-link-extended/list-link',

        data() {
            return _.extend({
                linkEntity: this.model.getFieldParam('name', 'linkEntity')
            }, Dep.prototype.data.call(this));
        }

    })
);

