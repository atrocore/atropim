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

Espo.define('pim:views/product/record/panels/product-channels', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            notLinkedWithProduct() {
                return this.model.id;
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:relate after:unrelate', link => {
                if (link === 'channels') {
                    $('.action[data-action=refresh][data-panel=productAttributeValues]').click();
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.collection.parentEntityId = this.model.get('id');

            this.listenTo(this.collection, 'change:isActiveEntity', model => {
                if (!model.hasChanged('modifiedAt')) {
                    this.notify('Saving...');
                    let value = model.get('isActiveEntity');
                    let data = {entityName: 'Product', value: value, entityId: this.collection.parentEntityId};
                    this.ajaxPutRequest('Channel/' + model.get('id') + '/setIsActiveEntity', data).then(response => {
                        this.notify('Saved', 'success');
                    });
                }
            });
        },

    })
);