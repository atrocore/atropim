

Espo.define('pim:views/fields/full-width-list-image', 'views/fields/image',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                this.$el.find('img').css({width: '100%'});
            }
        },

    })
);