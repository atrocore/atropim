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
Espo.define('pim:views/attribute-tab/record/panels/attributes', 'pim:views/product-family/record/panels/product-family-attributes',
    Dep => Dep.extend({

        prepareGroupCollection(group, collection) {
            group.rowList.forEach(id => {
                collection.add(this.collection.get(id));
            });

            collection.url = `AttributeTab/${this.model.id}/attributes`;

            this.listenTo(collection, 'sync', () => {
                collection.models.sort((a, b) => a.get('sortOrder') - b.get('sortOrder'));
            });

            return collection;
        },

        onGroupViewCreated(view) {
        },

        relateAttributes(selectObj) {
            const tabId = this.model.get('id');

            let promises = [];
            selectObj.forEach(attributeModel => {
                if (attributeModel.get('attributeTabId') !== tabId) {
                    attributeModel.set('attributeTabId', tabId);
                    promises.push(attributeModel.save());
                }
            });

            Promise.all(promises).then(() => {
                this.notify('Linked', 'success');
                this.actionRefresh();
            });
        },

    })
);