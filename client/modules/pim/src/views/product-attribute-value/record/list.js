

Espo.define('pim:views/product-attribute-value/record/list', 'views/record/list',
    Dep => Dep.extend({

        massActionsDisabled: true,

        rowActionsView: 'views/record/row-actions/empty'

    })
);

