

Espo.define('pim:views/channel/record/list-in-product', 'views/record/list',
    Dep => Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.setEditModeIsActiveEntityField();
        },

        showMoreRecords: function (collection, $list, $showMore, callback) {
            Dep.prototype.showMoreRecords.call(this, collection, $list, $showMore, () => {
                if (typeof callback === 'function') {
                    callback();
                }
                this.setEditModeIsActiveEntityField();
            });
        },

        setEditModeIsActiveEntityField() {
            (this.rowList || []).forEach(id => {
                const rowView = this.getView(id);
                if (rowView) {
                    const fieldView = rowView.getView('isActiveEntityField');
                    if (fieldView) {
                        fieldView.setMode('edit');
                        fieldView.reRender();
                    }
                }
            });
        }
    })
);