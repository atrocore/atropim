/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
                    view.getField('language')?.hide();
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
                        if (model.get('attributeId') && model.get('attributeIsMultilang') && !['extensibleEnum', 'extensibleMultiEnum'].includes(model.get('attributeType'))) {
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
