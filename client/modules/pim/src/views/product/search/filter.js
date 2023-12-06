/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'pim:product/search/filter',

        pinned: false,

        events: {
            'click a[data-action="pinFilter"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                this.pinned = !this.pinned;

                if (this.pinned) {
                    this.$el.find('.pin-filter').addClass('pinned');
                } else {
                    this.$el.find('.pin-filter').removeClass('pinned');
                }

                this.trigger('pin-filter', this.pinned);
            },
        },

        setup: function () {
            let name = this.name = this.options.name;
            name = name.split('-')[0];
            this.clearedName = name;
            let type = this.model.getFieldType(name) || this.options.params.type;
            this.pinned = this.options.pinned;

            if (type) {
                let viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);
                let params = {};

                if (type === 'unit') {
                    params.measureId = this.options.params.measureId
                    viewName = 'views/fields/unit-link'
                }

                if (type === 'extensibleEnum' || type === 'extensibleMultiEnum') {
                    params.extensibleEnumId = this.options.params.extensibleEnumId;
                }

                let options = {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    name: name,
                    params: params,
                    searchParams: this.options.searchParams,
                };

                if (type === 'link' || type === 'linkMultiple') {
                    options.foreignScope = this.options.params.foreignScope;
                }

                this.createView('field', viewName, options);
            }
        },

        data: function () {
            let isPinEnabled = true;

            if (this.getParentView() && this.getParentView().getParentView() && this.getParentView().getParentView()) {
                const parent = this.getParentView().getParentView();

                if (('layoutName' in parent) && parent.layoutName === 'listSmall') {
                    isPinEnabled = false;
                }
            }

            return _.extend({
                label: this.options.params.isAttribute ? this.options.params.label : this.getLanguage().translate(this.name, 'fields', this.scope),
                clearedName: this.clearedName,
                isPinEnabled: isPinEnabled,
                pinned: this.pinned
            }, Dep.prototype.data.call(this));
        }
    });
});

