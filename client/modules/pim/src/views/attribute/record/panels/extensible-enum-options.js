/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/record/panels/extensible-enum-options', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            this.defs.create = false;

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

            this.collection.url = this.getCollectionUrl();
            this.collection.urlRoot = this.getCollectionUrl();

            this.$el.parent().hide();
            if (['extensibleEnum', 'extensibleMultiEnum'].includes(this.model.attributeModel.get('type'))) {
                this.$el.parent().show();
            }
        },

    })
);