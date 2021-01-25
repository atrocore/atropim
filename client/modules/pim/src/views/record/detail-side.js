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

Espo.define('pim:views/record/detail-side', 'views/record/detail-side',
    Dep => Dep.extend({
        events: _.extend({
            'click a[data-action="changeOwnership"]': function (e) {
                let currentTarget = $(e.currentTarget);
                let name = currentTarget.data('name');
                let state = currentTarget.data('state');
                this.changeFieldOwnership(name, state === 'unlock');
                this.updateOwnership(name, state === 'lock');
            },
        }, Dep.prototype.events),

        afterRender() {
            let fields = this.getFieldViews();

            (Object.keys(fields) || []).forEach(field => {
                if (field in this.ownershipOptions) {
                    let config = this.getConfig().get(this.ownershipOptions[field].config);

                    if (config && config !== 'notInherit') {
                        fields[field].readOnly = true;
                        let unlock = !(this.model.get(this.ownershipOptions[field].field) === false ? this.model.get(this.ownershipOptions[field].field) : true);
                        this.changeFieldOwnership(fields[field].name, unlock);
                    }
                }
            });

            Dep.prototype.afterRender.call(this);
        },

        changeFieldOwnership(name, unlock) {
            if (name) {
                let fields = this.getFieldViews();

                if (name in fields) {
                    let field = fields[name],
                        cell = field.getCellElement();

                    cell.find('a[data-action="changeOwnership"]').remove();
                    let stateLink;
                    if (unlock) {
                        stateLink = $(`<a href="javascript:" data-name="${field.name}" data-action="changeOwnership" data-state="lock"
                        class="action pull-right lock-link" title="${this.translate('setFieldAsInheritable', 'labels', this.scope)}">
                        <span class="fas fa-unlink fa-sm"></span>
                    </a>`);

                        field.readOnlyLocked = false;
                        field.setNotReadOnly();

                        this.listenTo(field, 'inline-edit-on', () => {
                            stateLink.addClass('hidden');
                        });
                        this.listenTo(field, 'inline-edit-off', () => {
                            stateLink.removeClass('hidden');
                        });

                        if (this.getParentView().mode === 'edit') {
                            field.setMode('edit');
                            field.reRender();
                        }
                    } else {
                        stateLink = $(`<a href="javascript:" data-name="${field.name}" data-action="changeOwnership" data-state="unlock" 
                        class="action pull-right text-muted unlock-link" title="${this.translate('setFieldAsOwn', 'labels', this.scope)}">
                        <span class="fas fa-link fa-sm"></span>
                    </a>`);

                        field.setReadOnly();

                        field.removeInlineEditLinks();
                    }
                    cell.prepend(stateLink);
                }
            }
        },

        updateOwnership (field, remove) {
            if (field in this.ownershipOptions) {
                this.notify('Saving...');
                this.model.save({[this.ownershipOptions[field].field]: remove}, {
                    success: function () {
                        this.notify('Saved', 'success');
                        this.model.trigger('after:save');
                    }.bind(this),
                    patch: true
                });
            }
        },

    })
);
