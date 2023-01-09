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

        existsAttributes: [],

        selectedAttributesWithOneFilter: [],

        attrsFieldParams: {},

        events: _.extend({}, Dep.prototype.events, {
            'click .advanced-filters a.remove-attribute-filter': function (e) {
                e.stopPropagation();
                e.preventDefault();

                var $target = $(e.currentTarget);
                var name = $target.data('name');

                this.selectedAttributesWithOneFilter.splice(this.selectedAttributesWithOneFilter.indexOf($target.data('id')), 1);
                this.$el.find('a[data-id="' + name.split('-')[0] + '"]').parent().removeClass('hide');
                var container = this.getView('filter-' + name).$el.closest('div.filter');

                if (!(name in this.pinned) || this.pinned[name] === false) {
                    this.clearView('filter-' + name);
                    container.remove();
                    delete this.advanced[name];

                    this.presetName = this.primary;
                } else {
                    this.getView('filter-' + name).getView('field').clearSearch();
                }

                this.updateAddAttributeFilterButton();
                this.updateExpandListButtonInFamily();

                this.fetch();
                this.updateSearch();
                this.toggleFilterActionsVisibility();
                this.toggleResetVisibility();

                this.manageLabels();
                this.handleLeftDropdownVisibility();
                this.setupOperatorLabels();
            },
            'click .dropdown-menu a[data-action="savePreset"]': function () {
                this.createView('savePreset', 'Modals.SaveFilters', {}, function (view) {
                    view.render();
                    this.listenToOnce(view, 'save', function (name) {
                        this.savePreset(name);
                        view.close();
                        this.removeFilters();
                        this.createFilters(function () {
                            this.render();
                        }.bind(this));
                    }, this);
                }.bind(this));
            },
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
                            let nameCount = 1;
                            let getLastIndexName = () => {
                                if (this.advanced.hasOwnProperty(attribute.id + '-' + nameCount)) {
                                    nameCount++;
                                    getLastIndexName.call(this);
                                }
                            };
                            getLastIndexName.call(this);

                            let compiledName = attribute.id + '-attr-' + nameCount;
                            this.advanced[compiledName] = {};
                            this.advanced = this.sortAdvanced(this.advanced);

                            this.attrsFieldParams[attribute.id] = {
                                isAttribute: true,
                                label: attribute.get('name'),
                                type: attribute.get('type')
                            };

                            if (['enum', 'multiEnum'].includes(attribute.get('type'))) {
                                this.attrsFieldParams[attribute.id].isTypeValue = true;
                                this.attrsFieldParams[attribute.id].options = attribute.get('typeValueIds') || [];
                                this.attrsFieldParams[attribute.id].translatedOptions = {};
                                this.attrsFieldParams[attribute.id].options.forEach((option, k) => {
                                    this.attrsFieldParams[attribute.id].translatedOptions[option] = attribute.get('typeValue')[k];
                                });
                            }

                            this.createAttributeFilter(compiledName, {fieldParams: this.attrsFieldParams[attribute.id]}, view => {
                                view.populateDefaults();
                                this.fetch();
                                this.updateSearch();
                            });

                            this.handleLeftDropdownVisibility();
                            this.toggleFilterActionsVisibility();
                            this.toggleResetVisibility();

                            this.manageLabels();
                        });
                    });

                }
            },
            'click .dropdown-submenu a.expand-list': function (e) {
                $(e.target).next('ul').toggle();
                e.stopPropagation();
                e.preventDefault();
            }
        }),

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalFilters.push({
                name: 'addAttributeFilter',
                label: this.translate('addAttributeFilter', 'labels', 'Product')
            });

            this.ajaxGetRequest('Attribute/action/getAttributesIdsFilter')
                .then(function (response) {
                    this.existsAttributes = response;

                    for (var field in this.advanced) {
                        let fieldName = field.split('-').shift();

                        if (this.advanced[field].fieldParams && this.advanced[field].fieldParams.isAttribute && !this.existsAttributes.includes(fieldName)) {
                            let view = this.getView('filter-' + field);
                            if (view) {
                                view.remove();
                            }

                            delete this.advanced[field];
                        }
                    }

                    this.reRender();
                })
        },

        isFieldExist(name, filterField) {
            let field = name.split('-').shift();

            if (this.getMetadata().get(['entityDefs', this.scope, 'fields', field])) {
                return true;
            }

            return filterField && filterField.fieldParams && filterField.fieldParams.isAttribute;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.showFamiliesAttributes = this.getAcl().check('Attribute', 'read');

            return data;
        },

        fetch: function () {
            this.textFilter = (this.$el.find('input[name="textFilter"]').val() || '').trim();

            this.bool = {};

            this.boolFilterList.forEach(function (name) {
                this.bool[name] = this.$el.find('input[name="' + name + '"]').prop('checked');
            }, this);

            for (var field in this.advanced) {
                var view = this.getView('filter-' + field).getView('field');
                let fieldParams = this.advanced[field].fieldParams || {};
                this.advanced[field] = view.fetchSearch();
                if (fieldParams.isAttribute) {
                    this.advanced[field].fieldParams = fieldParams;
                }

                let fieldParts = field.split('-attr-');
                if (fieldParts.length === 2) {
                    if (this.advanced[field] === false) {
                        this.advanced[field] = {};
                    }
                    this.advanced[field] = _.extend({fieldParams: this.attrsFieldParams[fieldParts[0]]}, this.advanced[field]);
                }
                view.searchParams = this.advanced[field];
            }
        },

        updateCollection() {
            const defaultFilters = this.searchManager.get();
            const catalogTreeData = this.getCatalogTreeData();
            let extendedFilters = _.extend(Espo.Utils.cloneDeep(defaultFilters), catalogTreeData);
            this.searchManager.set(extendedFilters);

            Dep.prototype.updateCollection.call(this);

            this.searchManager.set(defaultFilters);
        },

        getCatalogTreeData() {
            let result = {};
            const list = this.getParentView();
            if (list) {
                const treePanel = list.getView('catalogTreePanel');
                if (treePanel && treePanel.catalogTreeData) {
                    result = treePanel.catalogTreeData;
                }
            }
            return result;
        },

        resetFilters() {
            Dep.prototype.resetFilters.call(this);

            this.selectedAttributesWithOneFilter = [];
            this.$el.find('.family-list li.hide').removeClass('hide');
        },

        createFilter: function (name, params, callback, noRender) {
            if (((params || {}).fieldParams || {}).isAttribute) {
                this.createAttributeFilter(name, params, callback);
            } else {
                Dep.prototype.createFilter.call(this, name, params, callback, noRender);
            }
        },

        createAttributeFilter: function (name, params, callback) {
            params = params || {};

            if (this.isRendered() && !this.$advancedFiltersPanel.find(`.filter.filter-${name}`).length) {
                var div = document.createElement('div');
                div.className = "filter filter-" + name + " col-sm-4 col-md-3";
                div.setAttribute("data-name", name);
                var nameIndex = name.split('-')[1];
                var beforeFilterName = name.split('-')[0] + '-' + (+nameIndex - 1);
                var beforeFilter = this.$advancedFiltersPanel.find('.filter.filter-' + beforeFilterName + '.col-sm-4.col-md-3')[0];
                var afterFilterName = name.split('-')[0] + '-' + (+nameIndex + 1);
                var afterFilter = this.$advancedFiltersPanel.find('.filter.filter-' + afterFilterName + '.col-sm-4.col-md-3')[0];
                if (beforeFilter) {
                    var nextFilter = beforeFilter.nextElementSibling;
                    if (nextFilter) {
                        this.$advancedFiltersPanel[0].insertBefore(div, beforeFilter.nextElementSibling);
                    } else {
                        this.$advancedFiltersPanel[0].appendChild(div);
                    }
                } else if (afterFilter) {
                    this.$advancedFiltersPanel[0].insertBefore(div, afterFilter);
                } else {
                    this.$advancedFiltersPanel[0].appendChild(div);
                }
            }

            this.createView('filter-' + name, 'pim:views/product/search/filter', {
                name: name,
                model: this.model,
                params: params.fieldParams,
                searchParams: params,
                el: this.options.el + ' .filter[data-name="' + name + '"]',
                pinned: this.pinned[name] || false
            }, function (view) {
                if (!this.selectedAttributesWithOneFilter.includes(name.split('-')[0])) {
                    this.selectedAttributesWithOneFilter.push(name.split('-')[0]);
                }

                if (typeof callback === 'function') {
                    view.once('after:render', function () {
                        callback(view);
                    });
                }

                if (this.isRendered()) {
                    view.listenToOnce(view, 'after:render', () => {
                        this.setupOperatorLabels();
                    });
                }
                view.render();

                this.listenTo(view, 'pin-filter', function (pinned) {
                    if (pinned) {
                        this.pinned[view.name] = pinned;
                    } else {
                        delete this.pinned[view.name];
                    }

                    this.updateSearch();
                });
            }.bind(this));
        },

        selectPreset: function (presetName, forceClearAdvancedFilters) {
            var wasPreset = !(this.primary == this.presetName);

            this.presetName = presetName;

            var advanced = this.getPresetData();
            this.primary = this.getPrimaryFilterName();

            var isPreset = !(this.primary === this.presetName);

            if (forceClearAdvancedFilters || wasPreset || isPreset || Object.keys(advanced).length) {
                if (Object.keys(this.pinned).length === 0) {
                    this.removeFilters();
                    this.advanced = advanced;
                }
            }

            this.updateSearch();
            this.manageLabels();

            this.createFilters();
            this.reRender();
            this.updateCollection();
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
