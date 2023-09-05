/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/modals/select-entity-and-records', 'views/modals/select-entity-and-records',
    Dep => Dep.extend({

        template: 'pim:product/modals/select-entity-and-records',

        validations: ['required'],

        setup() {
            Dep.prototype.setup.call(this);

            this.waitForView('association');
            this.createAssociationSelectView();
        },

        getDataForUpdateRelation(foreign, viewModel) {
            let data = Dep.prototype.getDataForUpdateRelation.call(this, foreign, viewModel);
            if (this.model.get('selectedLink') === 'associatedMainProducts') {
                data.associationId = viewModel.get('associationId');
            }
            return data;
        },

        createAssociationSelectView() {
            this.createView('association', 'pim:views/association/fields/backward-association', {
                el: `${this.options.el} .entity-container .field[data-name="association"]`,
                model: this.model,
                name: 'association',
                foreignScope: 'Association',
                inlineEditDisabled: true,
                mode: 'edit',
                defs: {
                    params: {
                        required: true
                    }
                },
                labelText: this.translate('association', 'fields', 'Product')
            }, view => {
                view.listenTo(view, 'after:render', () => {
                    this.checkScopeForAssociation();
                });
            });
        },

        reloadList(entity) {
            Dep.prototype.reloadList.call(this, entity);

            this.checkScopeForAssociation();
        },

        checkScopeForAssociation() {
            if (this.model.get('selectedLink') === 'associatedMainProducts') {
                this.getView('association').show();
            } else {
                this.getView('association').hide();
                this.model.set({
                    associationId: null,
                    associationName: null
                });
            }
        },

        getFieldViews() {
            let fields = {};
            if (this.hasView('association') && this.model.get('selectedLink') === 'associatedMainProducts') {
                fields['association'] = this.getView('association');
            }
            return fields;
        },

    })
);

