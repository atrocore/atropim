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

Espo.define('pim:views/product/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'pim:product/search/filter',

        setup: function () {
            let name = this.name = this.options.name;
            name = name.split('-')[0];
            this.clearedName = name;
            let type = this.model.getFieldType(name) || this.options.params.type;

            if (type) {
                let viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

                let params = {};
                if (this.options.params.isTypeValue) {
                    params = {
                        options: this.options.params.options,
                        translatedOptions: this.options.params.translatedOptions
                    }
                }
                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    name: name,
                    params: params,
                    searchParams: this.options.searchParams,
                });
            }
        },

        data: function () {
            return _.extend({
                label: this.options.params.isAttribute ? this.options.params.label : this.getLanguage().translate(this.name, 'fields', this.scope),
                clearedName: this.clearedName
            }, Dep.prototype.data.call(this));
        }
    });
});

