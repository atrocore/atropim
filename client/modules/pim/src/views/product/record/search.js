/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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

                            if (['link', 'linkMultiple'].indexOf(attribute.get('type')) >= 0) {
                                fieldParams.foreignScope = attribute.get('entityType');
                            }

                            if (attribute.get('type') === 'extensibleEnum' || attribute.get('type') === 'extensibleMultiEnum') {
                                fieldParams.extensibleEnumId = attribute.get('extensibleEnumId');
                            }

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
                    let id = field.split('-').shift()
                    if (id.endsWith("Unit") || id.endsWith("From")) id = id.substring(0, id.length - 4)
                    if (id.endsWith("To")) id = id.substring(0, id.length - 2)
                    attributesIds.push(id);
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
                                if ([record.id, record.id + 'From', record.id + 'To', record.id + 'Unit'].includes(id)) {
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

        getAttribute(attributeId) {
            let key = `attribute_${attributeId}`;
            if (!Espo[key]) {
                Espo[key] = null;
                this.ajaxGetRequest(`Attribute/${attributeId}`, null, {async: false}).success(attr => {
                    Espo[key] = attr;
                });
            }

            return Espo[key];
        },

        addFilter(name, params, callback) {
            if (params.fieldParams && params.fieldParams.isAttribute && !params.fieldParams.processed && ['rangeFloat', 'rangeInt', 'int', 'float', 'varchar'].indexOf(params.fieldParams.type) >= 0) {
                const measureId = this.getAttribute(name)?.measureId
                params.fieldParams.processed = true
                const fieldType = params.fieldParams.type;
                const unit = name + 'Unit'
                const paramsUnit = {
                    fieldParams: {
                        ...params.fieldParams,
                        type: 'unit',
                        measureId,
                        label: params.fieldParams.label + ' Unit'
                    }
                }

                if (['rangeFloat', 'rangeInt'].includes(fieldType)) {
                    const newType = fieldType === 'rangeFloat' ? 'float' : 'int';
                    const from = name + 'From'
                    const to = name + 'To'
                    const paramsFrom = {
                        fieldParams: {
                            ...params.fieldParams,
                            type: newType,
                            label: params.fieldParams.label + ' From'
                        }
                    }
                    const paramsTo = {
                        fieldParams: {
                            ...params.fieldParams,
                            type: newType,
                            label: params.fieldParams.label + ' To'
                        }
                    }
                    if (!this.filterAdded(from)) {
                        this.addFilter(from, paramsFrom, () => {
                            if (!this.filterAdded(to)) {
                                this.addFilter(to, paramsTo, () => {
                                    if (measureId && !this.filterAdded(unit)) {
                                        this.addFilter(unit, paramsUnit)
                                    }
                                })
                            } else if (measureId && !this.filterAdded(unit)) {
                                this.addFilter(unit, paramsUnit)
                            }
                        })
                    } else if (!this.filterAdded(to)) {
                        this.addFilter(from, paramsTo, () => {
                            if (measureId && !this.filterAdded(unit)) {
                                this.addFilter(unit, paramsUnit)
                            }
                        })
                    } else if (measureId && !this.filterAdded(unit)) {
                        this.addFilter(unit, paramsUnit)
                    }
                } else {
                    if (!this.filterAdded(name)) {
                        this.addFilter(name, params, () => {
                            if (measureId && !this.filterAdded(unit)) {
                                this.addFilter(unit, paramsUnit)
                            }
                        })
                    } else if (measureId && !this.filterAdded(unit)) {
                        this.addFilter(unit, paramsUnit)
                    }

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
                let filterView = this.getView('filter-' + field);
                if (filterView) {
                    let view = filterView.getView('field');
                    if (view) {
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
                }
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
