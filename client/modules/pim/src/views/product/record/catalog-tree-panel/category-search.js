

Espo.define('pim:views/product/record/catalog-tree-panel/category-search', 'view',
    Dep => Dep.extend({

        template: 'pim:product/record/catalog-tree-panel/category-search',

        data() {
            return {
                scope: this.scope
            }
        },

        setup() {
            this.scope = this.options.scope || this.scope;
        },

        afterRender() {
            if (this.el) {
                this.$el.find('input').autocomplete({
                    serviceUrl: function () {
                        return this.getAutocompleteUrl();
                    }.bind(this),
                    paramName: 'q',
                    minChars: 1,
                    autoSelectFirst: true,
                    transformResult: function (json) {
                        let response = JSON.parse(json);
                        let list = [];
                        response.list.forEach(category => {
                            let firstParentId;
                            if (category.categoryRoute) {
                                firstParentId = category.categoryRoute.split('|').find(element => element);
                            }
                            this.options.catalogs.forEach(catalog => {
                                if ((catalog.categoriesIds || []).includes(category.id) || (firstParentId && (catalog.categoriesIds || []).includes(firstParentId))) {
                                    let modifiedItem = Espo.Utils.cloneDeep(category);
                                    modifiedItem.value = catalog.name + ' > ' + modifiedItem.name;
                                    modifiedItem.catalogId = catalog.id;
                                    list.push(modifiedItem);
                                }
                            });
                        });
                        return {
                            suggestions: list
                        };
                    }.bind(this),
                    onSelect: function (category) {
                        this.$el.find('input').val('');
                        this.trigger('category-search-select', category);
                    }.bind(this)
                });
            }
        },

        getAutocompleteUrl() {
            let url = 'Category?sortBy=createdAt';
            let where = [];
            where.push({type: 'bool', value: ['onlyActive']});
            url += '&' + $.param({'where': where});
            return url;
        }

    })
);