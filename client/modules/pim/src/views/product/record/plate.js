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

Espo.define('pim:views/product/record/plate', 'pim:views/product/record/list',
    Dep => Dep.extend({

        template: 'pim:product/record/plate',

        type: 'plate',

        name: 'plate',

        listContainerEl: '.list > div > .plate > .row',

        itemsInRow: null,

        itemsInRowOptions: [],

        events: _.extend({
            'click .item-container': function (e) {
                const id = $(e.currentTarget).data('id');
                if (id
                    && !$.contains(this.$el.find(`.item-container[data-id="${id}"] .actions`).get(0), e.target)
                    && !$.contains(this.$el.find(`.item-container[data-id="${id}"] .field-name`).get(0), e.target)
                    && !$.contains(this.$el.find(`.item-container[data-id="${id}"] .field-preview`).get(0), e.target)
                ) {
                    e.stopPropagation();
                    e.preventDefault();

                    this.actionQuickView({id});
                }
            },
            'click [data-action="sortByDirection"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.sortByDirection();
            },
            'click [data-action="sortByField"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.sortByField($(e.currentTarget).data('name'));
            },
            'click [data-action="setItemsInRow"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.setItemsInRow($(e.currentTarget).data('name'));
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.initItemsInRow();
        },

        data() {
            return _.extend({
                sortFields: this.getSortFieldsList(),
                itemsInRowOptions: this.itemsInRowOptions,
                itemsInRow: this.itemsInRow,
                itemContainerWidth: 100 / this.itemsInRow
            }, Dep.prototype.data.call(this));
        },

        getSortFieldsList() {
            const fields = [];
            const fieldDefs = this.getMetadata().get(['entityDefs', this.scope, 'fields']);
            for (let field in fieldDefs) {
                if (!fieldDefs[field].disabled
                    && !fieldDefs[field].layoutListDisabled
                    && !this.getMetadata().get(['fields', fieldDefs[field].type, 'notSortable'])
                ) {
                    fields.push(field);
                }
            }

            fields.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));

            return fields;
        },

        buildRow(i, model, callback) {
            const key = model.id;

            this.rowList.push(key);

            const acl =  {
                edit: this.getAcl().checkModel(model, 'edit'),
                delete: this.getAcl().checkModel(model, 'delete')
            };
            this.createView(key, this.getItemView(), {
                model: model,
                acl: acl,
                el: this.options.el + ' .item-container[data-id="' + key +'"]',
                optionsToPass: ['acl'],
                noCache: true,
                name: this.type + '-' + model.name,
                setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered(),
                rowActionsView: this.rowActionsView
            }, callback);
        },

        getItemView() {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.plateItem') || 'views/asset/record/plate-item';
        },

        getRowSelector(id) {
            return `.item-container[data-id="${id}"]`;
        },

        getSelectAttributeList(callback) {
            callback(this.modifyAttributeList(['name', 'productStatus', 'type', 'imageId', 'imageName']));
        },

        getRowContainerHtml(id) {
            return `<div class="col-xs-6 col-sm-3 item-container" data-id="${id}"></div>`;
        },

        checkRecord(id, $target, isSilent) {
            Dep.prototype.checkRecord.call(this, id, $target, isSilent);

            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');
            if ($target.get(0)) {
                $target.closest('.plate-item').addClass('active');
            }
        },

        uncheckRecord(id, $target, isSilent) {
            Dep.prototype.uncheckRecord.call(this, id, $target, isSilent);

            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');
            if ($target.get(0)) {
                $target.closest('.plate-item').removeClass('active');
            }
        },

        selectAllHandler(isChecked) {
            Dep.prototype.selectAllHandler.call(this, isChecked);

            var plateItems = this.$el.find('.list .plate-item');
            if (isChecked) {
                plateItems.addClass('active');
            } else {
                plateItems.removeClass('active');
            }
        },

        selectAllResult() {
            Dep.prototype.selectAllResult.call(this);

            this.$el.find('.list .plate-item').addClass('active');
        },

        sortByDirection() {
            this.toggleSort(this.collection.sortBy);
        },

        sortByField(field) {
            var asc = this.collection.asc;

            this.notify('Please wait...');
            this.collection.once('sync', function () {
                this.notify(false);
                this.trigger('sort', {sortBy: field, asc: asc});
            }, this);
            var maxSizeLimit = this.getConfig().get('recordListMaxSizeLimit') || 200;
            while (this.collection.length > maxSizeLimit) {
                this.collection.pop();
            }
            this.collection.sort(field, asc);
            this.deactivate();
        },

        initItemsInRow() {
            const $window = $(window);
            this.itemsInRow = this.getStorage().get('itemsInRow', `plate-${this.scope}`) || this.getItemsInRowToWindowWidth($window.width());
            this.itemsInRowOptions = [1, 2, 3, 4, 5, 6];
        },

        setItemsInRow(count) {
            if (this.itemsInRow !== count) {
                this.itemsInRow = count;
                this.reRender();

                this.getStorage().set('itemsInRow', `plate-${this.scope}`, this.itemsInRow);
            }
        },

        getItemsInRowToWindowWidth(windowWidth) {
            let count = this.itemsInRow;
            if (windowWidth < 768) {
                count = 2;
            } else if (windowWidth < 992) {
                count = 3;
            } else if (windowWidth < 1200) {
                count = 4;
            } else {
                count = 5;
            }
            return count;
        }

    })
);

