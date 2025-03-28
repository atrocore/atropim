/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/detail', 'class-replace!pim:views/record/detail',
    Dep => Dep.extend({

        setup() {
            this.bottomView = this.getMetadata().get(`clientDefs.${this.scope}.bottomView.${this.type}`) || this.bottomView;

            Dep.prototype.setup.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let parentView = this.getParentView();
            if (parentView.options.params && parentView.options.params.setEditMode) {
                this.actionEdit();
            }
        },

        prepareLayoutData(data) {
            if (!this.getMetadata().get(`scopes.${this.model.name}.hasAttribute`)) {
                return;
            }

            if (!this.getAcl().check(this.model.name, 'read')) {
                return;
            }

            let params = {
                entityName: this.model.name,
                entityId: this.model.get('id')
            };

            let layoutRows = [];

            this.ajaxGetRequest('Attribute/action/recordAttributes', params, {async: false}).success(items => {
                let layoutRow = [];
                items.forEach(item => {
                    this.model.defs['fields'][item.id] = item;
                    layoutRow.push({
                        name: item.id,
                        customLabel: item.name,
                        fullWidth: ['text', 'markdown', 'wysiwyg'].includes(item.type)
                    });
                    if (layoutRow[0]['fullWidth'] || layoutRow[1]) {
                        layoutRows.push(layoutRow);
                        layoutRow = [];
                    }
                })
            })

            data.layout.forEach((row, k) => {
                if (row.id === 'attributeValues') {
                    delete data.layout[k];
                }
            })

            if (layoutRows.length > 0) {
                data.layout.push({
                    id: 'attributeValues',
                    label: this.translate('attributeValues'),
                    rows: layoutRows
                });
            }
        },

    })
);

