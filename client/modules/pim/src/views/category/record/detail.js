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

Espo.define('pim:views/category/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        template: 'pim:category/record/detail',

        notSavedFields: ['image'],

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.isWide && this.type !== 'editSmall' && this.type !== 'detailSmall') {
                this.isTreePanel = true;
                this.setupTreePanel();
            }

            this.listenTo(this.model, 'after:save', () => {
                this.model.fetch();
                $('.action[data-action=refresh][data-panel=catalogs]').click();
                $('.action[data-action=refresh][data-panel=channels]').click();
                $('.action[data-action=refresh][data-panel=productFamilyAttributes]').click();
            });
        },

        data() {
            return _.extend({isTreePanel: this.isTreePanel}, Dep.prototype.data.call(this))
        },

        setupTreePanel() {
            this.createView('treePanel', 'pim:views/category/record/tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: this.scope,
                model: this.model
            }, view => {
                view.listenTo(view, 'select-node', data => {
                    this.selectNode(data);
                });
                view.listenTo(view, 'tree-init', () => {
                    this.treeInit(view);
                });
            });
        },

        selectNode(data) {
            window.location.href = `/#${this.scope}/view/${data.id}`;
        },

        treeInit(view) {
            if (view.model && view.model.get('id')) {
                view.selectTreeNode(view.parseRoute(view.model.get('categoryRoute')), view.model.get('id'));
            }
        },

        save(callback, skipExit) {
            (this.notSavedFields || []).forEach(field => {
                const keys = this.getFieldManager().getAttributeList(this.model.getFieldType(field), field);
                keys.forEach(key => delete this.model.attributes[key]);
            });

            return Dep.prototype.save.call(this, callback, skipExit);
        },

    })
);

