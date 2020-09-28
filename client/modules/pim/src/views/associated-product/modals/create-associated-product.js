

Espo.define('pim:views/associated-product/modals/create-associated-product', 'pim:views/modals/edit-without-side',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', mainModel => {
                if (mainModel.get('bothDirections')) {
                    this.getModelFactory().create(mainModel.name, newModel => {
                        let attributes = mainModel.getClonedAttributes();
                        newModel.set({
                            associationId: attributes.backwardAssociationId,
                            associationName: attributes.backwardAssociationName,
                            mainProductId: attributes.relatedProductId,
                            mainProductName: attributes.relatedProductName,
                            relatedProductId: attributes.mainProductId,
                            relatedProductName: attributes.mainProductName
                        });
                        newModel.save().then(response => {
                            if (this.scope === this.getParentView().scope) {
                                this.getParentView().collection.fetch();
                            }
                        });
                    });
                }
            });
        }
    })
);

