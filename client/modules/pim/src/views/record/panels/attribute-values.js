/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/attribute-values', 'views/record/panels/relationship',
    Dep => Dep.extend({

        // setup() {
        //     Dep.prototype.setup.call(this);
        //
        //     this.defs.selectAction = 'selectAttribute';
        // },

        actionSelectAttribute(data) {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'Attribute',
                multiple: true,
                createButton: false
                // filters: filters,
                // primaryFilterName: primaryFilterName,
                // boolFilterList: boolFilterList,
                // boolFilterData: boolFilterData,
                // selectDuplicateEnabled: selectDuplicateEnabled
            }, dialog => {
                dialog.render();
                this.notify(false);

                dialog.once('select', selectObj => {
                    console.log(selectObj);

                    // var data = {shouldDuplicateForeign: duplicate};
                    // if (Object.prototype.toString.call(selectObj) === '[object Array]') {
                    //     var ids = [];
                    //     selectObj.forEach(function (model) {
                    //         ids.push(model.id);
                    //     });
                    //     data.ids = ids;
                    // } else {
                    //     if (selectObj.massRelate) {
                    //         data.massRelate = true;
                    //         data.where = selectObj.where;
                    //     } else {
                    //         data.id = selectObj.id;
                    //     }
                    // }
                    //
                    // const selectConfirm = this.getMetadata().get(`clientDefs.${self.scope}.relationshipPanels.${link}.selectConfirm`) || false;
                    // if (selectConfirm) {
                    //     let parts = selectConfirm.split('.');
                    //     Espo.Ui.confirm(this.translate(parts[2], parts[1], parts[0]), {
                    //         confirmText: self.translate('Apply'),
                    //         cancelText: self.translate('Cancel')
                    //     }, () => {
                    //         this.createLink(this.scope, this.model.id, link, data);
                    //     });
                    // } else {
                    //     this.createLink(this.scope, this.model.id, link, data);
                    // }
                });
            });
        },

    })
);
