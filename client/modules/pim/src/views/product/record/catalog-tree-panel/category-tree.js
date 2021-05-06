/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

Espo.define('pim:views/product/record/catalog-tree-panel/category-tree', 'view',
    Dep => Dep.extend({

        template: 'pim:product/record/catalog-tree-panel/category-tree',

        loadedCategories: [],

        expandableCategory: null,

        events: {
            'show.bs.collapse div.panel-collapse.collapse[class^="catalog-"]': function (e) {
                e.stopPropagation();
                $(e.currentTarget).prev('button.catalog-link').find('span.caret').addClass('caret-up');
                this.$el.parent().find(`.panel-collapse.collapse[class^="catalog-"].in`).collapse('hide');
            },
            'hide.bs.collapse div.panel-collapse.collapse[class^="catalog-"]': function (e) {
                e.stopPropagation();
                $(e.currentTarget).prev('button.catalog-link').find('span.caret').removeClass('caret-up');
            },
            'show.bs.collapse div.panel-collapse.collapse[class^="category-"]': function (e) {
                e.stopPropagation();
            },
            'shown.bs.collapse div.panel-collapse.collapse[class^="category-"]': function (e) {
                e.stopPropagation();
                this.unfold($(e.currentTarget).data('id'));
            },
            'hide.bs.collapse div.panel-collapse.collapse[class^="category-"]': function (e) {
                e.stopPropagation();
            },
            'hidden.bs.collapse div.panel-collapse.collapse[class^="category-"]': function (e) {
                e.stopPropagation();
                this.fold($(e.currentTarget).data('id'));
            },
            'click button.category.child-category': function (e) {
                const id = $(e.currentTarget).data('id');
                this.setCategoryActive(id);
                this.selectCategory(id);
            }
        },
        
        data() {
            return {
                catalog: this.options.catalog,
                rootCategoriesList: this.getRootCategoriesList(),
                hash: this.getRandomHash()
            }
        },

        setup() {
            this.categories = this.options.categories || [];
            this.catalog = this.options.catalog;
            this.loadedCategories = [];
        },

        getRootCategoriesList() {
            return this.categories
                .filter(category => this.catalog.categoriesIds.includes(category.id))
                .map(category => {
                    return {
                        id: category.id,
                        html: category.childrenCount ? this.getParentHtml(category, this.isRendered()) : this.getChildHtml(category)
                    };
                });
        },

        getParentHtml(category, fullLoad) {
            let hash = this.getRandomHash();
            let html = '';
            if (fullLoad) {
                (category.childs || []).forEach(child => {
                    html += child.childrenCount ? this.getParentHtml(child, (child.childs || []).length) : this.getChildHtml(child);
                });
            }
            return `
                <li data-id="${category.id}" class="list-group-item child">
                    <button class="btn btn-link category category-icons" data-toggle="collapse" data-target=".category-${hash}" data-id="${category.id}" data-name="${category.name}">
                        <span class="fas fa-angle-right"></span>
                        <span class="fas fa-angle-down hidden"></span>
                    </button>
                    <button class="btn btn-link category child-category" data-id="${category.id}">
                        ${category.name}
                    </button>
                    <div class="category-${hash} panel-collapse collapse" data-id="${category.id}">
                        <ul class="list-group list-group-tree">${html}</ul>
                    </div>
                </li>`;
        },

        getChildHtml(category) {
            return `
                <li data-id="${category.id}" class="list-group-item child">
                    <button class="btn btn-link category child-category" data-id="${category.id}" data-name="${category.name}">
                        ${category.name}
                    </button>
                </li>`;
        },

        getRandomHash() {
            return Math.floor((1 + Math.random()) * 0x100000000)
                .toString(16)
                .substring(1);
        },

        fold(id) {
            let button = this.$el.find(`button.category-icons[data-id="${id}"]`);
            button.find('span.fa-angle-right').removeClass('hidden');
            button.find('span.fa-angle-down').addClass('hidden');
        },

        unfold(id) {
            this.setupCategoryTree(id, () => {
                let button = this.$el.find(`button.category-icons[data-id="${id}"]`);
                button.find('span.fa-angle-right').addClass('hidden');
                button.find('span.fa-angle-down').removeClass('hidden');
                this.expandCategoriesFromRoute(id);
            });
        },

        setupCategoryTree(id, callback) {
            let promise;
            let category = this.loadedCategories.find(item => item.id === id);
            if (!category) {
                promise = new Promise(resolve => {
                    this.getTreeCategories(id, categories => {
                        category = this.categories.find(item => item.id === id);
                        this.categories = this.categories.concat(categories);
                        this.setCategoryChilds(category, categories);
                        resolve();
                    });
                });
            } else {
                promise = new Promise(resolve => resolve());
            }
            promise.then(() => {
                this.buildCategoryHtml(category);
                callback();
            });
        },

        getTreeCategories(id, callback) {
            this.getFullEntity('Category', {
                select: 'name,categoryParentId,categoryRoute,childrenCount,sortOrder',
                where: [
                    {
                        type: 'equals',
                        attribute: 'categoryParentId',
                        value: id
                    }
                ],
                sortBy: 'sortOrder',
                asc: true
            }, categories => {
                callback(categories);
            });
        },

        setCategoryChilds(category, categories) {
            category.childs = categories;
            this.loadedCategories.push(category);
        },

        buildCategoryHtml(category) {
            let button = this.$el.find(`button.category-icons[data-id="${category.id}"]`);
            let listEl = button.parent().find(`.panel-collapse[data-id="${category.id}"] .list-group-tree`);
            if (!listEl.find('li').size()) {
                let html = '';
                category.childs.forEach(item => {
                    html += item.childrenCount ? this.getParentHtml(item) : this.getChildHtml(item);
                });
                listEl.append(html);
            }
        },

        getFullEntity(url, params, callback, container) {
            if (url) {
                container = container || [];

                let options = params || {};
                options.maxSize = options.maxSize || 200;
                options.offset = options.offset || 0;

                this.ajaxGetRequest(url, options).then(response => {
                    container = container.concat(response.list || []);
                    options.offset = container.length;
                    if (response.total > container.length || response.total === -1) {
                        this.getFullEntity(url, options, callback, container);
                    } else {
                        callback(container);
                    }
                });
            }
        },

        expandCategoryHandler(category) {
            if (typeof category === 'string') {
                category = this.categories.find(item => item.id === category);
            }
            if (category) {
                if (this.$el.size()) {
                    this.expandCategory(category);
                } else {
                    this.listenTo(this, 'after:render', () => this.expandCategory(category));
                }
            }
        },

        expandCategory(category) {
            this.expandableCategory = category;
            let catalogCollapse = this.$el.find('.collapse[class^="catalog-"]');
            catalogCollapse.collapse("show");
            let routes = (category.categoryRoute || '').split('|').filter(element => element);
            if (routes.length) {
                routes.some(routeId => {
                    const nextCollapse = catalogCollapse.find(`.collapse[data-id="${routeId}"]`);
                    if (nextCollapse.hasClass('in')) {
                        this.expandCategoriesFromRoute(routeId);
                        return false;
                    } else {
                        nextCollapse.collapse('show');
                        return true;
                    }
                });
            } else {
                this.setCategoryActive(this.expandableCategory.id);
            }
        },

        expandCategoriesFromRoute(categoryId) {
            if (this.expandableCategory) {
                let routes = (this.expandableCategory.categoryRoute || '').split('|').filter(element => element);
                let atLeastOne = routes.some(routeCategoryId => {
                    let nextCollapse = this.$el.find(`.collapse[data-id="${routeCategoryId}"]:not(.in)`);
                    if (categoryId !== routeCategoryId && nextCollapse.size()) {
                        nextCollapse.collapse('show');
                        return true;
                    }
                });
                if (!atLeastOne && this.expandableCategory) {
                    let expandableCategory = this.$el.find(`.category[data-id="${this.expandableCategory.id}"]`);
                    if (expandableCategory.size()) {
                        this.setCategoryActive(this.expandableCategory.id);
                        this.expandableCategory = null;
                    }
                }
            }
        },

        selectCategory(category) {
            if (typeof category === 'string') {
                category = this.categories.find(item => item.id === category);
                category.catalogId = this.catalog.id;
            }
            this.trigger('category-tree-select', category);
        },

        setCategoryActive(id) {
            if (id) {
                let panel = this.$el.parents('.category-panel');
                panel.find('.category-buttons > button').removeClass('active');
                panel.find('li.child.active').removeClass('active');
                this.$el.find(`li.child[data-id="${id}"]`).addClass('active');
            }
        }

    })
);