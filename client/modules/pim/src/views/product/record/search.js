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

Espo.define('pim:views/product/record/search', ['views/record/search', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        template: 'pim:product/record/search',

        familiesAttributes: [],

        existsAttributes: [],

        attributesDownloaded: false,

        selectedAttributesWithOneFilter: [],

        events: _.extend({}, Dep.prototype.events, {
            'click a[data-action="addAttributeFilter"]': function (e) {
                var $target = $(e.currentTarget);
                var id = $target.data('id');
                var nameCount = 1;
                var getLastIndexName = function () {
                    if (this.advanced.hasOwnProperty(id + '-' + nameCount)) {
                        nameCount++;
                        getLastIndexName.call(this);
                    }
                };
                getLastIndexName.call(this);
                let compiledName = id + '-' + nameCount;
                this.advanced[compiledName] = {};
                this.advanced = this.sortAdvanced(this.advanced);

                this.presetName = this.primary;
                let params = this.getAttributeParams(compiledName);
                if (this.typesWithOneFilter.includes(params.fieldParams.type) && !this.selectedAttributesWithOneFilter.includes(id)) {
                    this.selectedAttributesWithOneFilter.push(id);
                }
                this.createAttributeFilter(compiledName, params, function (view) {
                    view.populateDefaults();
                    this.fetch();
                    this.updateSearch();
                }.bind(this));
                this.updateAddAttributeFilterButton();
                this.updateExpandListButtonInFamily();
                this.handleLeftDropdownVisibility();

                this.manageLabels();
            },
            'click .advanced-filters a.remove-attribute-filter': function (e) {
                var $target = $(e.currentTarget);
                var name = $target.data('name');

                this.selectedAttributesWithOneFilter.splice(this.selectedAttributesWithOneFilter.indexOf($target.data('id')), 1);
                this.$el.find('a[data-id="' + name.split('-')[0] + '"]').parent().removeClass('hide');
                var container = this.getView('filter-' + name).$el.closest('div.filter');
                this.clearView('filter-' + name);
                container.remove();
                delete this.advanced[name];

                this.presetName = this.primary;

                this.updateAddAttributeFilterButton();
                this.updateExpandListButtonInFamily();

                this.fetch();
                this.updateSearch();

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
            'click .dropdown-submenu > a.add-attribute-filter-button': function (e) {
                e.stopPropagation();
                e.preventDefault();

                let a = $(e.currentTarget);
                a.parents('.dropdown-menu').find('> .dropdown-submenu > a:not(.add-attribute-filter-button)').next('ul').toggle(false);
                if (this.attributesDownloaded) {
                    a.next('ul').toggle();
                } else {
                    if (this.getAcl().check('Attribute', 'read')) {
                        this.$el.find('.family-list .no-family-data').text(this.translate('Loading...', 'labels', 'Global'));
                        this.$el.find('.family-list').toggle();

                        this.ajaxGetRequest('Attribute/action/filtersData')
                            .then(response => {
                                this.attributesDownloaded = true;
                                this.familiesAttributes = response;
                            })
                            .always(() => {
                                this.listenToOnce(this, 'after:render', () => {
                                    this.$el.find('.left-dropdown').addClass('open');
                                    this.$el.find('.family-list').toggle();
                                });
                                this.reRender();
                            });

                    }
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

            return !!(this.getMetadata().get(['entityDefs', this.scope, 'fields', field]) || filterField.fieldParams.isAttribute);
        },

        data() {
            var data = Dep.prototype.data.call(this);

            data.familiesAttributes = this.familiesAttributes;
            data.showFamiliesAttributes = this.getAcl().check('Attribute', 'read');
            return data;
        },

        hideAttributesWithOneFilter() {
            this.familiesAttributes.some(family => family.rows.some(row => {
                if (this.selectedAttributesWithOneFilter.includes(row.attributeId)) {
                    this.$el.find(`a[data-id="${row.attributeId}"]`).parent().addClass('hide');
                }
            }));
        },

        getAttributeParams(name) {
            let params = {
                isAttribute: true
            };
            this.familiesAttributes.some(family => family.rows.some(row => {
                if (row.attributeId === name.split('-')[0]) {
                    params.label = row.name;
                    params.type = row.type;
                    if (['enum', 'multiEnum'].includes(row.type)) {
                        params.isTypeValue = true;
                        params.options = row.typeValue;
                        row.typeValue.forEach(value => {
                            params.translatedOptions = params.translatedOptions || {};
                            params.translatedOptions[value] = this.getLanguage().translateOption(value, row.name, 'Attribute');
                        });
                    }
                    return true;
                }
            }));
            return {fieldParams: params};
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
                this.familiesAttributes.forEach(function (family) {
                    family.rows.forEach(function (row) {
                        let name = field.split('-')[0];
                        if (row.attributeId === name) {
                            if (this.advanced[field] === false) {
                                this.advanced[field] = {};
                            }
                            this.advanced[field] = _.extend(this.getAttributeParams(name), this.advanced[field]);
                        }
                    }, this);
                }, this);
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

        updateAddAttributeFilterButton: function () {
            var $ul = this.$el.find('ul.family-list');
            if ($ul.children().not('.hide').size() == 0) {
                this.$el.find('a.add-attribute-filter-button').addClass('disabled');
            } else {
                this.$el.find('a.add-attribute-filter-button').removeClass('disabled');
            }
        },

        updateExpandListButtonInFamily: function () {
            this.$el.find('ul.attribute-filter-list').each((i, el) => {
                let ul = $(el);
                if (ul.children().not('.hide').size() === 0) {
                    ul.parent().find('a.expand-list').addClass('hide');
                } else {
                    ul.parent().find('a.expand-list').removeClass('hide');
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.updateAddAttributeFilterButton();
            this.updateExpandListButtonInFamily();
            this.addAutoCompleteToAttributeSearch();
            this.hideAttributesWithOneFilter();
        },

        addAutoCompleteToAttributeSearch() {
            let attrSearchElement = this.$el.find('.attribute-text-filter');
            if (attrSearchElement.length) {
                let attributes = [];
                (this.familiesAttributes || []).forEach(family => {
                    (family.rows || []).forEach(attribute => {
                        if (!attributes.find(item => (item.data || {}).attributeId === attribute.attributeId)) {
                            attributes.push({value: attribute.name, data: attribute});
                        }
                    });
                });
                attrSearchElement.autocomplete({
                    appendTo: attrSearchElement.parents('ul.family-list'),
                    lookup: attributes,
                    paramName: 'q',
                    minChars: 1,
                    onSelect: function (s) {
                        attrSearchElement.val('');
                        var name = (s.data || {}).attributeId;
                        var nameCount = 1;
                        var getLastIndexName = function () {
                            if (this.advanced.hasOwnProperty(
                                name + '-' + nameCount)) {
                                nameCount++;
                                getLastIndexName.call(this);
                            }
                        };
                        getLastIndexName.call(this);
                        name = name + '-' + nameCount;
                        this.advanced[name] = {};
                        this.advanced = this.sortAdvanced(this.advanced);

                        this.presetName = this.primary;
                        this.createAttributeFilter(name, this.getAttributeParams(name), function (view) {
                            view.populateDefaults();
                            this.fetch();
                            this.updateSearch();
                        }.bind(this));
                        this.updateAddAttributeFilterButton();
                        this.updateExpandListButtonInFamily();
                        this.handleLeftDropdownVisibility();

                        this.manageLabels();
                    }.bind(this)
                });
                $(attrSearchElement.autocomplete().suggestionsContainer).css({top: attrSearchElement.outerHeight(true)});
                this.once('render remove', function () {
                    attrSearchElement.autocomplete('dispose');
                    attrSearchElement.val('');
                }, this);
                this.listenToOnce(this.getRouter(), 'routed', () => {
                    attrSearchElement.autocomplete('dispose');
                    attrSearchElement.val('');
                });
                this.$el.find('.add-attribute-filter-button').click(function () {
                    attrSearchElement.autocomplete().hide();
                    attrSearchElement.val('');
                });
            }
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
                el: this.options.el + ' .filter[data-name="' + name + '"]'
            }, function (view) {
                if (!this.selectedAttributesWithOneFilter.includes(name.split('-')[0])) {
                    this.selectedAttributesWithOneFilter.push(name.split('-')[0]);
                }
                this.hideAttributesWithOneFilter();

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
            }.bind(this));
        },

        selectPreset: function (presetName, forceClearAdvancedFilters) {
            var wasPreset = !(this.primary == this.presetName);

            this.presetName = presetName;

            var advanced = this.getPresetData();
            this.primary = this.getPrimaryFilterName();

            var isPreset = !(this.primary === this.presetName);

            if (forceClearAdvancedFilters || wasPreset || isPreset || Object.keys(advanced).length) {
                this.removeFilters();
                this.advanced = advanced;
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
