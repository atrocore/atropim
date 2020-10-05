Espo.define('pim:views/product/record/row-actions/product-channels', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            if (this.model.get('isFromCategoryTree') === true) {
                list = list.filter(item => item.action !== 'unlinkRelated' && item.action !== 'removeRelated');
            }

            return list;
        }
    })
);
