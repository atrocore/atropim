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

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (this.getAcl().check(this.entityType, 'edit') && this.getMetadata().get(`scopes.${this.model.name}.hasAttribute`)) {
                this.dropdownItemList.push({
                    label: 'addAttribute',
                    name: 'addAttribute'
                });
            }
        },

        actionAddAttribute(data) {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'Attribute',
                multiple: true,
                createButton: false,
                massRelateEnabled: false
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', selectObj => {
                    this.notify('Loading...');
                    const data = {
                        entityName: this.model.name,
                        entityId: this.model.get('id'),
                    }
                    if (Array.isArray(selectObj)) {
                        data.ids = selectObj.map(o => o.id)
                    } else {
                        data.where = selectObj.where
                    }
                    $.ajax({
                        url: `Attribute/action/addAttributeValue`,
                        type: 'POST',
                        data: JSON.stringify(data),
                        contentType: 'application/json',
                        success: () => {
                            this.refreshLayout();
                            this.notify('Saved', 'success');
                        }
                    });
                });
            });
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
                    this.model.defs['fields'][item.name] = item;
                    layoutRow.push({
                        name: item.name,
                        customLabel: item.label,
                        fullWidth: ['text', 'markdown', 'wysiwyg', 'script'].includes(item.type)
                    });
                    if (layoutRow[0]['fullWidth'] || layoutRow[1]) {
                        layoutRows.push(layoutRow);
                        layoutRow = [];
                    }
                })

                if (layoutRow.length > 0) {
                    layoutRow.push(false);
                    layoutRows.push(layoutRow);
                }
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

