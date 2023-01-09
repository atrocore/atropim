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

Espo.define('pim:views/catalog/record/list', 'pim:views/record/list',
    Dep => Dep.extend({

        massActionRemove: function () {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            var self = this;

            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml(),
            }, function () {
                this.notify('Removing...');

                var ids = [];
                var data = {};
                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhere();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.ids = ids;
                }

                for (var i in this.checkedList) {
                    ids.push(this.checkedList[i]);
                }

                $.ajax({
                    url: this.entityType + '/action/massDelete',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    result = result || {};
                    var count = result.count;
                    if (this.allResultIsChecked) {
                        if (count) {
                            this.unselectAllResult();
                            this.listenToOnce(this.collection, 'sync', function () {
                                var msg = 'massRemoveResult';
                                if (count == 1) {
                                    msg = 'massRemoveResultSingle'
                                }
                                Espo.Ui.success(this.translate(msg, 'messages').replace('{count}', count));
                            }, this);
                            this.collection.fetch();
                            Espo.Ui.notify(false);
                        } else {
                            Espo.Ui.warning(self.translate('noRecordsRemoved', 'messages'));
                        }
                    } else {
                        var idsRemoved = result.ids || [];
                        if (count) {
                            idsRemoved.forEach(function (id) {
                                Espo.Ui.notify(false);
                                this.checkedList = [];

                                this.collection.trigger('model-removing', id);
                                this.removeRecordFromList(id);
                                this.uncheckRecord(id, null, true);

                            }, this);
                            var msg = 'massRemoveResult';
                            if (count == 1) {
                                msg = 'massRemoveResultSingle'
                            }
                            Espo.Ui.success(self.translate(msg, 'messages').replace('{count}', count));
                        } else {
                            Espo.Ui.warning(self.translate('noRecordsRemoved', 'messages'));
                        }
                    }
                }.bind(this));
            }, this);
        },

        actionQuickRemove: function (data) {
            data = data || {};
            var id = data.id;
            if (!id) return;

            var model = this.collection.get(id);
            if (!this.getAcl().checkModel(model, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml()
            }, function () {
                this.collection.trigger('model-removing', id);
                this.collection.remove(model);
                this.notify('Removing...');
                model.destroy({
                    wait: true,
                    success: function () {
                        this.notify('Removed', 'success');
                        this.removeRecordFromList(id);
                    }.bind(this),
                    error: function() {
                        this.notify('Error occured', 'error');
                        this.collection.push(model);
                    }.bind(this),
                    complete: function () {
                        this.collection.fetch();
                    }.bind(this)
                });
            }, this);
        },

        getBodyHtml() {
            return '' +
                '<div class="row">' +
                    '<div class="col-xs-12">' +
                        '<span class="confirm-message">' + this.translate('removeRecordConfirmation', 'messages') + '</span>' +
                    '</div>' +
                    '<div class="col-xs-12">' +
                        '<div class="cell pull-left" style="margin-top: 15px;">' +
                            '<label class="control-label">' + this.translate('removeHint', 'labels', 'Attribute') + '</label>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

    })
);

