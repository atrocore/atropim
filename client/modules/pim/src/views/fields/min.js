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

Espo.define('pim:views/fields/min', 'views/fields/float', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.urlRoot === 'ClassificationAttribute') {
                this.listenTo(this.model, 'change:attributeId', () => {
                    this.reRender();
                });
            }

            if (this.model.urlRoot === 'Attribute') {
                this.listenTo(this.model, 'change:type', () => {
                    this.reRender();
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['detail', 'edit'].includes(this.mode) && (this.model.has('type') || this.model.has('attributeId'))) {
                let scope = this.model.urlRoot,
                    forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(scope, 'read');

                if (!forbiddenFieldList.includes(this.name)) {
                    this.hide();
                    if (this.model.urlRoot === 'Attribute') {
                        this.toggleVisibility(this.model.get('type'));
                    } else if (this.model.urlRoot === 'ClassificationAttribute') {
                        if (this.model.get('attributeId')) {
                            this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attribute => {
                                this.toggleVisibility(attribute.type);
                                if (this.mode === 'edit' && this.model.isNew() && attribute.min) {
                                    this.model.set('min', attribute.min);
                                }
                            });
                        }
                    }
                }
            }
        },

        toggleVisibility(type) {
            if (['rangeInt', 'rangeFloat'].includes(type)) {
                this.show();
            }
        },

    });

});
