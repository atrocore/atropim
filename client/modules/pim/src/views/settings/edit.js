Espo.define('pim:views/settings/edit', 'views/settings/edit', function (Dep) {

    return Dep.extend({

        scope: 'Settings',

        recordView: 'pim:views/admin/settings',

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > .header',
                template: 'pim:admin/settings/headers/settings'
            });
        }

    });

});

