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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('pim:views/product/modals/attributes-for-mass-update', 'views/modal', function (Dep) {

    return Dep.extend({
        template: 'modals/edit',

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'addAttribute',
                    label: 'Add',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.scope || this.options.scope;
            this.layoutName = this.options.layoutName || 'detailSmall';

            this.waitForView('edit');

            this.getModelFactory().create(this.scope, function (model) {
                if (this.options.attributes) {
                    model.set(this.options.attributes);
                }

                this.model = model;

                this.createRecordView(model);
            }.bind(this));

            this.header = this.translate('Product', 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update') + ' &raquo ' + this.getLanguage().translate(this.scope, 'scopeNamesPlural');
        },

        createRecordView: function (model, callback) {
            var viewName = this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editSmall']) || 'views/record/edit-small';

            var options = {
                model: model,
                el: this.containerSelector + ' .edit-container',
                type: 'editSmall',
                layoutName: this.layoutName,
                columnCount: this.columnCount,
                buttonsDisabled: true,
                sideDisabled: true,
                bottomDisabled: true,
                exit: function () {}
            };
            this.createView('edit', viewName, options, (view) => {
                if (callback) {
                    callback();
                }

                view.listenTo(view, 'after:render', (view) => {
                    view.getField('language').hide();
                });

                view.listenTo(view.model, 'change:attributeId', function (model) {
                    const language = view.getField('language');

                    if (model.get('attributeId') && (model.get('attributeIsMultilang') === undefined || model.get('type') === undefined)) {
                        this.ajaxGetRequest(`Attribute/${model.get('attributeId')}`, {}, {async: false}).then(result => {
                            model.set('attributeIsMultilang', result.isMultilang);
                            model.set('attributeType', result.type);
                        });
                    }

                    if (language) {
                        if (model.get('attributeId') && model.get('attributeIsMultilang') && !['enum', 'multiEnum'].includes(model.get('attributeType'))) {
                            language.setNotReadOnly();
                            language.show();

                            language.setMode('edit');
                            language.reRender();
                        } else {
                            language.hide();
                        }
                    }
                }.bind(view))
            });
        },

        actionAddAttribute() {
            let editView = this.getView('edit');

            if (editView.validate()) {
                return false;
            }

            this.trigger('add-attribute', editView.model);
        }
    })
});
