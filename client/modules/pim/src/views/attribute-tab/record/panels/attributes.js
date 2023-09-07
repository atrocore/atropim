/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('pim:views/attribute-tab/record/panels/attributes', 'pim:views/classification/record/panels/classification-attributes',
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