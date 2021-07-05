Espo.define('pim:views/category/record/row-actions/channels', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);
            if (this.getParentView().getParentView().getParentView().model.get('categoryParentId')) {
                list = list.filter(item => item.action !== 'unlinkRelated' && item.action !== 'removeRelated');
            }

            return list;
        }

    })
);
