

Espo.define('pim:views/dashlets/fields/varchar-with-url', 'views/fields/varchar',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/varchar-with-url/list',

        events: {
            'click a': function (event) {
                event.stopPropagation();
                event.preventDefault();
                let hash = event.currentTarget.hash;
                let name = this.model.get(this.name);
                let options = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).options;
                this.getRouter().navigate(hash, {trigger: false});
                this.getRouter().dispatch(hash.substr(1), 'list', options);
            }
        },

        data() {
            let name = this.model.get(this.name);
            let url = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).url;

            return {
                hasUrl: !!url,
                label: (this.model.getFieldParam(this.name, 'labelMap') || {})[name] || name,
                url: url
            }
        }

    })
);

