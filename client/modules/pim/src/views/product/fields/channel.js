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

Espo.define('pim:views/product/fields/channel', 'views/fields/enum',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:render', function () {
                this.controlViewVisibility(this.model.get('template'))
            })

            this.listenTo(this.model, 'changedTemplate', function () {
                this.controlViewVisibility(this.model.get('template'))
            }.bind(this));
        },

        controlViewVisibility(template) {
            if (template) {
                let category = this.model.get('entitiesIds') ? 'templatesEntities' : 'templates';
                let data = this.getMetadata().get(['pdfGenerator', 'Product', category, template]);

                if (!data || !('isChannelTemplate' in data) || data['isChannelTemplate'] === false) {
                    this.hide();
                } else {
                    this.show();
                }
            } else {
                this.hide();
            }
        }
    })
);
