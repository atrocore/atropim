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

Espo.define('pim:views/attribute/record/panels/extensible-enum-options', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            this.defs.create = false;

            Dep.prototype.setup.call(this);

            this.collection.url = this.getCollectionUrl();
            this.collection.urlRoot = this.getCollectionUrl();

            this.actionList = [];

            this.buttonList.push({
                title: 'Create',
                action: 'createExtensibleEnumOption',
                html: '<span class="fas fa-plus"></span>'
            });

            this.listenTo(this.model.attributeModel, 'after:save', () => {
                this.actionRefresh();
            });
        },

        actionCreateExtensibleEnumOption() {
            this.notify('Loading...');
            this.createView('quickCreate', 'views/modals/edit', {
                scope: 'ExtensibleEnumOption',
                fullFormDisabled: true,
                attributes: {
                    extensibleEnumId: this.model.attributeModel.get('extensibleEnumId'),
                    extensibleEnumName: this.model.attributeModel.get('extensibleEnumName')
                },
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.actionRefresh();
                });
            });
        },

        actionRefresh: function () {
            this.collection.url = this.getCollectionUrl();
            this.collection.urlRoot = this.getCollectionUrl();

            this.collection.fetch();
        },

        getCollectionUrl(){
            let extensibleEnumId = this.model.attributeModel.get('extensibleEnumId') || 'no-such-id';
            
            return `ExtensibleEnum/${extensibleEnumId}/extensibleEnumOptions`;
        },

        afterRender() {
            Dep.prototype.setup.call(this);

            this.$el.parent().hide();
            if (['extensibleEnum', 'extensibleMultiEnum'].includes(this.model.attributeModel.get('type'))) {
                this.$el.parent().show();
            }
        },

    })
);