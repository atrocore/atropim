

Espo.define('pim:views/product/fields/image', 'views/fields/image',
    Dep => Dep.extend({


        afterRender() {
            Dep.prototype.afterRender.call(this);
            if (this.mode === 'list')
            {
                this.$el.find('img').css({'max-height': '120px', 'max-width': '100%'});
            } else if(this.mode === 'detail')
            {
                this.$el.find('.attachment-preview').css({'display': 'block'});
                this.$el.find('img').css({'display': 'block', 'margin': '0 auto'});
            }
        },
    })
);
