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

Espo.define('pim:views/dashlets/fields/colored-varchar-with-url', 'pim:views/dashlets/fields/varchar-with-url',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/colored-varchar-with-url/list',

        data() {
            let name = this.model.get(this.name);
            let fieldName = this.options.defs.params.filterField;
            let backgroundcolors = this.getMetadata().get(['entityDefs', 'Product', 'fields', fieldName, 'optionColors']) || {};
            return _.extend({
                backgroundColor: backgroundcolors[name],
                color: this.getFontColor(backgroundcolors[name])
            }, Dep.prototype.data.call(this));
        },

        getFontColor(backgroundColor) {
            if (backgroundColor) {
                let color;
                let r = parseInt(backgroundColor.substr(0, 2), 16);
                let g = parseInt(backgroundColor.substr(2, 2), 16);
                let b = parseInt(backgroundColor.substr(4, 2), 16);
                let l = 1 - ( 0.299 * r + 0.587 * g + 0.114 * b) / 255;
                l < 0.5 ? color = '000' : color = 'fff';
                return color;
            }
        }

    })
);

