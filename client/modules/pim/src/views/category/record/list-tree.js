

Espo.define('pim:views/category/record/list-tree', ['view', 'lib!JsTree'], function (Dep) {
    return Dep.extend({

        _template: '',

        categoryList: null,

        categoryTree: null,

        resizeEventName: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.categoryList = [];
            this.categoryTree = [];

            this.wait(true);
            this.fetchCategoriesList(() => {
                this.wait(false);
            });

            this.resizeEventName = `resize.drag-n-drop-tree-${this.cid}`;
            this.listenToOnce(this, 'remove', () => {
                $(window).off(this.resizeEventName)
            });
        },

        fetchCategoriesList(callback) {
            this.collection.fetch({remove: false, more: true}).then(response => {
                if (this.collection.total > this.collection.length) {
                    this.fetchCategoriesList(callback);
                } else {
                    this.setupTree();
                    this.wait(false);
                }
            });
        },

        setupTree() {
            this.collection.models.forEach(model => this.categoryList.push(model.attributes));
            this.categoryTree = this.getCategoryTree(this.categoryList);
        },

        getSelectAttributeList: function (callback) {
            callback([]);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.tree({
                data: this.categoryTree,
                dragAndDrop: window.innerWidth >= 768,
                useContextMenu: false,
                closedIcon: $('<i class="fa fa-angle-right"></i>'),
                openedIcon: $('<i class="fa fa-angle-down"></i>')
            });

            $(window).off(this.resizeEventName).on(this.resizeEventName, () => {
                this.$el.tree('setOption', 'dragAndDrop', window.innerWidth >= 768);
            });

            this.$el.on('tree.move', e => {
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

                this.ajaxPatchRequest(`${this.collection.name}/${moveInfo.moved_node.id}`, data).then(response => {
                    moveInfo.do_move();
                });
            });
        },

        getCategoryTree(categoryList) {
            let sorter = function (collection) {
                collection.sort((a, b) => {
                    if (a.sortOrder < b.sortOrder) {
                        return -1;
                    }
                    if (a.sortOrder > b.sortOrder) {
                        return 1;
                    }
                    return 0;
                });
            };

            let rootCategories = categoryList.filter(item => !item.categoryRoute);
            sorter(rootCategories);

            let getParentsWithChildren = (parents) => {
                if (parents.length) {
                    let children = [];
                    categoryList.forEach(item => {
                        if (parents.find(parent => parent.id === item.categoryParentId)) {
                            children.push(item);
                        }
                    });

                    if (children.length) {
                        children = getParentsWithChildren(children);
                    }

                    sorter(children);

                    children.forEach(child => {
                        let parent = parents.find(parent => parent.id === child.categoryParentId);
                        if (parent) {
                            parent.children = parent.children || [];
                            parent.children.push(child);
                        }
                    });
                }
                return parents;
            };

            return getParentsWithChildren(rootCategories);
        },

    });
});
