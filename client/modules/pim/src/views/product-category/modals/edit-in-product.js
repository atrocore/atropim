

Espo.define('pim:views/product-category/modals/edit-in-product', 'views/modals/edit',
    Dep => Dep.extend({

        createRecordView: function (model, callback) {
            let productModel = this.options.relate.model;
            model.catalogId = productModel.get('catalogId');

            Dep.prototype.createRecordView.call(this, model, callback);
        },

    })
);