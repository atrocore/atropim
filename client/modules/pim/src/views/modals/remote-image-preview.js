

Espo.define('pim:views/modals/remote-image-preview', 'views/modals/image-preview',
    Dep => Dep.extend({

        data() {
            return {
                name: this.options.url,
                url: this.options.url,
                originalUrl: this.options.url
            };
        },

    })
);

