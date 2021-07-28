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

Espo.define('pim:views/category/record/tree-panel', ['view', 'lib!JsTree'],
    Dep => Dep.extend({

        template: 'pim:category/record/tree-panel',

        events: {
            'click button[data-action="collapsePanel"]': function () {
                this.actionCollapsePanel();
            }
        },

        setup() {
            this.scope = this.options.scope || this.scope;

            this.wait(true);
            this.buildSearch();
            this.wait(false);
        },

        data() {
            return {
                scope: this.scope,
                isPanel: !this.options.isModalView,
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.buildTree();

            if ($(window).width() <= 767 || !!this.getStorage().get('catalog-tree-panel', this.scope)) {
                this.actionCollapsePanel();
            }
        },

        selectTreeNode($tree, route, model) {
            if (route.length > 0) {
                let first = route.shift();
                $tree.tree('openNode', $tree.tree('getNodeById', first), () => {
                    this.selectTreeNode($tree, route, model);
                });
            } else {
                $tree.tree('selectNode', $tree.tree('getNodeById', model.get('id')));
            }
        },

        buildTree() {
            const self = this;
            const $tree = this.$el.find('.category-tree');

            $tree.tree({
                dataUrl: 'Category/action/Tree',
                selectable: true,
                dragAndDrop: true,
                useContextMenu: false,
                closedIcon: $('<i class="fa fa-angle-right"></i>'),
                openedIcon: $('<i class="fa fa-angle-down"></i>')
            }).on('tree.init', () => {
                    if (self.model && self.model.get('id')) {
                        let route = [];
                        (self.model.get('categoryRoute') || '').split('|').forEach(item => {
                            if (item) {
                                route.push(item);
                            }
                        });

                        self.selectTreeNode($tree, route, self.model);
                    }
                }
            ).on('tree.move', e => {
                e.preventDefault();
                let moveInfo = e.move_info;
                let data = {
                    _position: moveInfo.position,
                    _target: moveInfo.target_node.id,
                    categoryParentId: null,
                    categoryParentName: null
                };

                if (moveInfo.position === 'inside') {
                    data.categoryParentId = moveInfo.target_node.id;
                    data.categoryParentName = moveInfo.target_node.name;
                } else if (moveInfo.target_node.parent.id) {
                    data.categoryParentId = moveInfo.target_node.parent.id;
                    data.categoryParentName = moveInfo.target_node.parent.name;
                }

                this.ajaxPatchRequest(`Category/${moveInfo.moved_node.id}`, data).success(response => {
                    moveInfo.do_move();
                    this.model.trigger('category-moved');
                });
            }).on('tree.click', e => {
                e.preventDefault();

                if (this.options.isModalView) {
                    this.getModelFactory().create(null, model => {
                        model.id = e.node.id;
                        model.set('name', e.node.name);

                        this.getParentView().trigger('select', model);
                        this.getParentView().close();
                    });
                } else {
                    window.location.href = `/#Category/view/${e.node.id}`;
                }
            });
        },

        buildSearch() {
            let elSelector = '.catalog-tree-panel > .category-panel > .category-search';
            if (this.options.isModalView) {
                elSelector = '.modal-tree > .category-panel > .category-search';
            }

            this.createView('categorySearch', 'pim:views/category/record/tree-panel/category-search', {
                el: elSelector,
                scope: this.scope
            }, view => {
                view.render();
                this.listenTo(view, 'category-search-select', category => {
                    if (this.options.isModalView) {
                        this.getModelFactory().create(null, model => {
                            model.id = category.id;
                            model.set('name', category.name);

                            this.getParentView().trigger('select', model);
                            this.getParentView().close();
                        });
                    } else {
                        window.location.href = `/#Category/view/${category.id}`;
                    }
                });
            });
        },

        actionCollapsePanel(forceHide) {
            let categoryPanel = this.$el.find('.category-panel');
            if (categoryPanel.hasClass('hidden') && !forceHide) {
                categoryPanel.removeClass('hidden');
                $('.page-header').addClass('collapsed').removeClass('not-collapsed');
                $('.list-container').addClass('collapsed');
                this.showUtilityElements();
                this.getStorage().set('catalog-tree-panel', this.scope, '');
            } else {
                categoryPanel.addClass('hidden');

                $('.page-header').removeClass('collapsed').addClass('not-collapsed');
                $('.list-container').removeClass('collapsed');
                this.hideUtilityElements();
                this.getStorage().set('catalog-tree-panel', this.scope, 'collapsed');
            }
            $(window).trigger('resize');
        },

        showUtilityElements() {
            let button = this.$el.find('button[data-action="collapsePanel"]');
            button.removeClass('collapsed');
            button.find('span.toggle-icon-left').removeClass('hidden');
            button.find('span.toggle-icon-right').addClass('hidden');

            this.$el.removeClass('catalog-tree-panel-hidden');

            this.$el.addClass('col-xs-12 col-lg-3');
            if (this.model) {

                let detailContainer = this.$el.parents('#main').find('.overview');
                detailContainer.removeClass('col-lg-9');
                detailContainer.addClass('col-lg-6');

            } else {
                let listContainer = this.$el.parents('#main').find('.list-container');
                listContainer.addClass('col-xs-12 col-lg-9');
            }
        },

        hideUtilityElements() {
            let button = this.$el.find('button[data-action="collapsePanel"]');
            button.addClass('collapsed');
            button.find('span.toggle-icon-left').addClass('hidden');
            button.find('span.toggle-icon-right').removeClass('hidden');

            this.$el.addClass('catalog-tree-panel-hidden');

            this.$el.removeClass('col-xs-12 col-lg-3');
            if (this.model) {

                let detailContainer = this.$el.parents('#main').find('.overview');
                detailContainer.addClass('col-lg-9');
                detailContainer.removeClass('col-lg-6');

            } else {
                let listContainer = this.$el.parents('#main').find('.list-container');
                listContainer.removeClass('col-xs-12 col-lg-9');
            }
        },

    })
);