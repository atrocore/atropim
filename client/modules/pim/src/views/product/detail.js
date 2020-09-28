

Espo.define('pim:views/product/detail', 'pim:views/detail',
    Dep => Dep.extend({

        selectRelatedFilters: {},

        selectBoolFilterLists: {
            attributes: ['notLinkedWithProduct'],
        },

        boolFilterData: {
            attributes: {
                notLinkedWithProduct() {
                    return this.model.id;
                },
            },
        },

        actionNavigateToRoot(data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(function () {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                if (rootUrl !== `#${this.scope}`) {
                    this.getRouter().navigate(rootUrl,  {trigger: true});
                } else {
                    const options = {
                        isReturn: true
                    };
                    this.getRouter().navigate(rootUrl, {trigger: false});
                    this.getRouter().dispatch(this.scope, null, options);
                }
            }, this);
        }

    })
);

