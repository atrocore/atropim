

Espo.define('pim:views/header', 'class-replace!pim:views/header',
    Dep => Dep.extend({

        setup() {
            if (this.model && !this.model.isNew() && this.getMetadata().get(['scopes', this.model.name, 'advancedFilters']) &&
                !this.baseOverviewFilters.find(filter => filter.name === 'channelsFilter') && this.model.name === 'Product' &&
                this.getAcl().check('ProductAttributeValue', 'read') && this.getAcl().check('Channel', 'read')) {
                this.baseOverviewFilters.push({
                    name: 'channelsFilter',
                    view: 'pim:views/fields/overview-channels-filter'
                });
            }

            Dep.prototype.setup.call(this);
        }

    })
);
