/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/associated-main-product', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        setup() {
            this.relationName = 'AssociatedProduct';
            this.selectFields = ['id', 'name', 'mainImageId', 'mainImageName'];
            Dep.prototype.setup.call(this);
            this.relationship.scope = 'Product';
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, (relationModel) => {
                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }

                let data = {
                    select: this.selectFields.join(','),
                    where: [
                        {
                            type: 'linkedWith',
                            attribute: 'associatedRelatedProduct',
                            subQuery: [
                                {
                                    attribute: 'mainProductId',
                                    value: this.collection.models.map(m => m.id)
                                }
                            ]
                        }
                    ]
                };

                data.totalOnly = true;
                this.ajaxGetRequest('Product', data).success((res) => {

                    data.maxSize = 500 * this.collection.models.length;

                    if (res.total > data.maxSize) {
                        this.hasToManyRecords = true;
                        callback();
                        return;
                    }

                    data.totalOnly = false;
                    data.collectionOnly = true;

                    Promise.all([
                        this.ajaxGetRequest('Product', data),

                        this.ajaxGetRequest(this.relationName, {
                            maxSize: data.maxSize,
                            where: [
                                {
                                    type: 'in',
                                    attribute: 'mainProductId',
                                    value: this.collection.models.map(m => m.id)
                                }
                            ]
                        })]
                    ).then(results => {
                        let relationList = results[1].list;
                        let uniqueList = {};
                        results[0].list.forEach(v => uniqueList[v.id] = v);
                        this.linkedEntities = Object.values(uniqueList);
                        let hasRelation = {};
                        this.linkedEntities.forEach(item => {
                            this.relationModels[item.id] = [];
                            this.collection.models.forEach((model, key) => {
                                let m = relationModel.clone()
                                m.set(this.isLinkedColumns, false);
                                relationList.forEach(relationItem => {
                                    if (item.id === relationItem['relatedProductId'] && model.id === relationItem['mainProductId']) {
                                        hasRelation[item.id] = true
                                        m.set(relationItem);
                                        m.set(this.isLinkedColumns, true);
                                    }
                                });

                                this.relationModels[item.id].push(m);
                            })
                        });

                        this.linkedEntities = this.linkedEntities.filter(item => hasRelation[item.id]);

                        callback();
                    });
                });
            });
        },

        getFieldColumns(linkEntity) {
            let data = Dep.prototype.getFieldColumns.call(this, linkEntity);
            let key = linkEntity.id + 'MainImage';
            data.push({
                label: '',
                isField: true,
                key: key,
                entityValueKeys: []
            });

            this.getModelFactory().create('Product', model => {
                model.set(linkEntity);
                let viewName = model.getFieldParam('mainImage', 'view') || this.getFieldManager().getViewName(model.getFieldType('mainImage'));
                this.createView(key, viewName, {
                    el: `${this.options.el} [data-key="${key}"] .attachment-preview`,
                    model: model,
                    readOnly: true,
                    defs: {
                        name: 'mainImage',
                    },
                    mode: 'detail',
                    inlineEditDisabled: true,
                }, view => {
                    view.previewSize = 'small';
                })
            });

            return data;
        },
    });
});