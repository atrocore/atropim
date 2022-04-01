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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('pim:views/product-attribute-value/fields/is-pav-value-inherited', 'views/fields/bool',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                if (['enum', 'multiEnum'].includes(this.model.get('attributeType')) && this.model.get('language') !== 'main') {
                    this.$el.html('');
                    return;
                }

                let isPavValueInherited = this.model.get('isPavValueInherited');
                if (isPavValueInherited === true) {
                    this.$el.html(`<a href="javascript:" data-pavid="${this.model.get('id')}" class="action unlock-link" title="${this.translate('inherited')}"><span class="fas fa-link fa-sm"></span></a>`);
                } else if (isPavValueInherited === false) {
                    this.$el.html(`<a href="javascript:" data-pavid="${this.model.get('id')}" data-action="setPavAsInherited" class="action lock-link" title="${this.translate('setAsInherited')}"><span class="fas fa-unlink fa-sm"></span></a>`);
                } else {
                    this.$el.html('');
                }
            }
        },

    })
);

