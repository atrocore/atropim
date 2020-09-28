

Espo.define('pim:views/associated-product/record/row-actions/edit-and-remove-in-product', 'views/record/row-actions/relationship',
    Dep=> Dep.extend({

        getActionList() {
            let list = [];
            if (this.getAcl().check('Product', 'edit')) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    }
                }, {
                    action: 'quickRemove',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        }

    })
);


