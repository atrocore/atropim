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

Espo.define('pim:views/product/record/plate-item', 'view',
    Dep => Dep.extend({

        template: 'pim:product/record/plate-item',

        fields: ['productStatus', 'image'],

        events: _.extend({
            'click .link': function (e) {
                e.stopPropagation();
                e.preventDefault();
                const url = $(e.currentTarget).attr('href');
                this.getRouter().navigate(url, {trigger: false});
                this.getRouter().dispatch(this.model.name, 'view', {
                    id: this.model.id,
                    rootUrl: `${this.model.name}/plate`,
                    model: this.model
                });
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.rowActionsView) {
                this.waitForView('rowActions');
                this.createView('rowActions', this.options.rowActionsView, {
                    el: `${this.options.el} .actions`,
                    model: this.model,
                    acl: this.options.acl
                });
            }

            this.createFields();
        },

        data() {
            return {

            };
        },

        createFields() {
            this.fields.forEach(field => {
                const view = `${field}Field`;
                const type = this.model.getFieldParam(field, 'type');
                const viewName = this.model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);

                this.waitForView(view);
                this.createView(view, viewName, {
                    el: `${this.options.el} [data-name="${field}"]`,
                    model: this.model,
                    mode: 'list',
                    name: field
                }, () => {
                    if (field === 'image') {
                        this.model.trigger('updateProductImage');
                    }
                });
            });
        }

    })
);

