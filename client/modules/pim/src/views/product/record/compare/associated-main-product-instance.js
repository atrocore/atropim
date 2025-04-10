/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/associated-main-product-instance',
    ['views/record/compare/relationship-instance', 'pim:views/product/record/compare/associated-main-product'],
    (Dep, AssociatedMain) => Dep.extend({
        setup() {
            this.relationName = 'AssociatedProduct';
            this.selectFields = ['id', 'name', 'mainImageId', 'mainImageName'];
            Dep.prototype.setup.call(this);
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, relationModel => {
                let modelRelationColumnId = this.getModelRelationColumnId();
                let relationshipRelationColumnId = this.getRelationshipRelationColumnId();
                let relationFilter = {
                    maxSize: this.maxTobeshown,
                    where: [
                        {
                            type: 'equals',
                            attribute: modelRelationColumnId,
                            value: this.model.id
                        }
                    ],
                };

                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }

                let entityFilter = {
                    max: this.maxTobeshown,
                    select: this.selectFields.join(','),
                    where: [
                        {
                            type: 'linkedWith',
                            attribute: 'associatedRelatedProduct',
                            subQuery: [
                                {
                                    type: 'equals',
                                    attribute: 'mainProductId',
                                    value: this.model.id
                                }
                            ]
                        }
                    ]
                };

                Promise.all([
                    this.ajaxGetRequest(this.scope, entityFilter),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        uri: this.scope + '?' + $.param(entityFilter),
                        type: 'list'
                    }),
                    this.ajaxGetRequest(this.relationName, relationFilter),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        uri: this.relationName + '?' + $.param(relationFilter),
                        type: 'list'
                    }),
                ]).then(results => {
                    if (results[0].total > this.maxTobeshown) {
                        this.hasToManyRecords = true;
                        callback();
                        return;
                    }

                    for (const result of results[1]) {
                        if (results.total > this.maxTobeshown) {
                            this.hasToManyRecords = true;
                            callback();
                            return;
                        }
                    }

                    let entities = {};
                    results[0].list.forEach(item => {
                        item['isLocal'] = true;
                        entities[item.id] = item;
                    });

                    results[1].forEach((resultPerInstance, index) => {
                        return resultPerInstance.list.forEach(item => {
                            if (!entities[item.id]) {
                                item['isDistant'] = true;
                                entities[item.id] = this.setBaseUrlOnFile(item, index);
                            } else {
                                entities[item.id]['isDistant'] = true;
                            }
                        });
                    });

                    this.linkedEntities = Object.values(entities);

                    let allRelationEntities = results[3].map(item => item.list);

                    allRelationEntities.unshift(results[2].list);
                    let hasRelation = {};

                    this.linkedEntities.forEach(entity => {
                        this.relationModels[entity.id] = [];
                        allRelationEntities.forEach(relationList => {
                            let m = relationModel.clone();
                            m.set(this.isLinkedColumns, false);
                            let relData = relationList.find(relationItem => entity.id === relationItem[relationshipRelationColumnId] && this.model.id === relationItem[modelRelationColumnId]);
                            if (relData) {
                                m.set(relData);
                                m.set(this.isLinkedColumns, true);
                                hasRelation[entity.id] = true
                            }
                            this.relationModels[entity.id].push(m);
                        });
                    });

                    this.linkedEntities = this.linkedEntities.filter(item => hasRelation[item.id]);

                    callback();
                });
            });
        },

        getModelRelationColumnId() {
            return 'mainProductId';
        },

        getRelationshipRelationColumnId() {
            return 'relatedProductId';
        },

        getLinkName() {
            return 'associatedProducts';
        },

        getFieldColumns(linkedEntity) {
           return AssociatedMain.prototype.getFieldColumns.call(this, linkedEntity);
        },
    })
);