

Espo.define('pim:views/product-family/record/list', 'pim:views/record/list',
    Dep => Dep.extend({

        rowActionsView: 'pim:views/product-family/record/row-actions/optioned-remove',

        massActionRemove() {
            if (!this.allResultIsChecked && this.checkedList && this.checkedList.length) {
                let isSystemInSelected = this.checkedList.some(item => {
                    let model = this.collection.get(item);
                    return model && model.get('isSystem');
                });
                if (isSystemInSelected) {
                    Espo.Ui.warning(this.translate('Can\'t remove system Product Family', 'messages', this.scope));
                    return;
                }
            }

            Dep.prototype.massActionRemove.call(this);
        }

    })
);