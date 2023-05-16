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

Espo.define('pim:views/product/record/search', 'views/record/search', Dep => Dep.extend({

    events: _.extend({}, Dep.prototype.events, {
        'click .dropdown-submenu > a[data-filter-name="addAttributeFilter"]': function (e) {
            e.stopPropagation();
            e.preventDefault();

            this.$leftDropdown.click();

            const scope = 'Attribute';
            if (this.getAcl().check(scope, 'read')) {
                const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';
                this.notify('Loading...');
                this.createView('dialog', viewName, {
                    scope: scope,
                    multiple: false,
                    createButton: false,
                    massRelateEnabled: false
                }, dialog => {
                    dialog.render();
                    this.notify(false);
                    dialog.once('select', attribute => {
                        let fieldParams = {
                            filterView: 'pim:views/product/search/filter',
                            isAttribute: true,
                            label: attribute.get('name'),
                            type: attribute.get('type')
                        };

                        this.addFilter(attribute.id, {fieldParams: fieldParams});
                    });
                });
            }
        },
        'click .advanced-filters a.remove-attribute-filter': function (e) {
            e.stopPropagation();
            e.preventDefault();

            var $target = $(e.currentTarget);
            var name = $target.data('name');

            this.$el.find('a[data-id="' + name.split('-')[0] + '"]').parent().removeClass('hide');
            var container = this.getView('filter-' + name).$el.closest('div.filter');

            this.clearView('filter-' + name);
            container.remove();
            delete this.advanced[name];

            if (name in this.pinned) {
                delete this.pinned[name];
            }

            this.presetName = this.primary;

            this.fetch();
            this.updateSearch();
            this.toggleFilterActionsVisibility();
            this.toggleResetVisibility();

            this.manageLabels();
            this.handleLeftDropdownVisibility();
            this.setupOperatorLabels();

            this.search();
        }
    }),

    setup() {
        Dep.prototype.setup.call(this);

        this.additionalFilters.push({
            name: 'addAttributeFilter',
            label: this.translate('addAttributeFilter', 'labels', 'Product')
        });
    },

    getFilterDataList: function () {
        /**
         * Collect attributes filters
         */
        let attributesIds = [];
        let attributesFields = [];
        $.each(this.advanced, (field, fieldData) => {
            if (fieldData.fieldParams && fieldData.fieldParams.isAttribute) {
                attributesIds.push(field.split('-').shift());
                attributesFields.push(field);
            }
        });

        /**
         * Remove attributes filters if such attributes does not exist anymore
         */
        if (attributesIds.length > 0) {
            const where = [{
                type: "in",
                attribute: "id",
                value: attributesIds
            }];
            this.ajaxGetRequest(`Attribute`, {where: where}, {async: false}).success(response => {
                if (response.list) {
                    attributesIds.forEach((id, k) => {
                        let found = false;
                        response.list.forEach(record => {
                            if (record.id === id) {
                                found = true;
                            }
                        });
                        if (!found) {
                            delete this.advanced[attributesFields[k]];
                        }
                    });
                }
            });
        }

        return Dep.prototype.getFilterDataList.call(this);
    },

    isFieldExist(name, filterField) {
        if (filterField.fieldParams && filterField.fieldParams.isAttribute) {
            return true;
        }

        return Dep.prototype.isFieldExist.call(this, name, filterField);
    },

    filterAdded(name) {
        return !!Object.keys(this.advanced).find(k => k.startsWith(name + '-'))
    },

    addFilter(name, params, callback) {
        if (params.fieldParams && ['rangeFloat', 'rangeInt'].indexOf(params.fieldParams.type) >= 0) {
            const fieldType = params.fieldParams.type;
            const newType = fieldType === 'rangeFloat' ? 'float' : 'int';
            const from = name + 'From'
            const to = name + 'To'
            const paramsFrom = {fieldParams: {...params.fieldParams, type: newType, label: params.fieldParams.label + ' From'}}
            const paramsTo = {fieldParams: {...params.fieldParams, type: newType, label: params.fieldParams.label + ' To'}}
            if (!this.filterAdded(from)) {
                this.addFilter(from, paramsFrom, function () {
                    if (!this.filterAdded(to)) {
                        this.addFilter(to, paramsTo)
                    }
                }.bind(this))
            } else if (!this.filterAdded(to)) {
                this.addFilter(from, paramsTo)
            }
            return
        }

        return Dep.prototype.addFilter.call(this, name, params, callback);
    },

    fetch: function () {
        this.textFilter = (this.$el.find('input[name="textFilter"]').val() || '').trim();

        this.bool = {};

        this.boolFilterList.forEach(function (name) {
            this.bool[name] = this.$el.find('input[name="' + name + '"]').prop('checked');
        }, this);

        for (let field in this.advanced) {
            let view = this.getView('filter-' + field).getView('field');
            this.advanced[field] = view.fetchSearch();

            let fieldParams = view.options.searchParams.fieldParams || {};
            if (fieldParams.isAttribute) {
                if (this.advanced[field] === false) {
                    this.advanced[field] = {};
                }
                this.advanced[field]['fieldParams'] = fieldParams;
            }
            view.searchParams = this.advanced[field];
        }
    },


    updateCollection() {
        const defaultFilters = Espo.Utils.cloneDeep(this.searchManager.get());

        const list = this.getParentView();
        const catalogTreePanel = list.getView('catalogTreePanel');
        if (catalogTreePanel && catalogTreePanel.catalogTreeData) {
            const extendedFilters = Espo.Utils.cloneDeep(defaultFilters);
            $.each(catalogTreePanel.catalogTreeData, (key, value) => {
                extendedFilters[key] = _.extend({}, extendedFilters[key], value);
            });
            this.searchManager.set(extendedFilters);
        }

        Dep.prototype.updateCollection.call(this);

        this.searchManager.set(defaultFilters);
    }

})
);
