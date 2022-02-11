Espo.define('pim:views/catalog/record/row-actions/categories', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            if (this.model.get('categoryParentId')) {
                list = list.filter(item => item.action !== 'unlinkRelated');
            }

            return list;
        }

    })
);
