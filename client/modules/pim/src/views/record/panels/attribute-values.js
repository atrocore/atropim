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
                    selectObj.forEach(model => {
                        let attrs = {attributeId: model.id, _silentMode: true}
                        attrs[this.lcFirst(this.model.name) + 'Id'] = this.model.id;
                        this.ajaxPostRequest(`FooAttributeValue`, attrs, {async: false});
                    });

                    this.notify('Linked', 'success');
                    this.model.trigger('after:relate', this.link, this.defs);
                    this.actionRefresh();
                });
            });
        },

        lcFirst(str) {
            if (!str) return str;
            return str.charAt(0).toLowerCase() + str.slice(1);
        },

    })
);
