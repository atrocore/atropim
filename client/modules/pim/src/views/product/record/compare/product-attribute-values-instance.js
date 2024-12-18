/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/product-attribute-values-instance',
    ['pim:views/product/record/compare/product-attribute-values', 'views/record/compare/relationship-instance'], function (Dep, Relationship) {
        return Dep.extend({

            fetchModelsAndSetup() {
                this.wait(true)
                this.getModelFactory().create('ProductAttributeValue', function (model) {
                    let param = {
                        tabId: this.tabId,
                        productId: this.baseModel.get('id'),
                        fieldFilter: ['allValues'],
                        languageFilter: ['allLanguages'],
                        scopeFilter: ['allChannels']
                    };
                    this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', param).success(res => {
                        let currentGroupPavs = res;
                        let tmp = {}

                        this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                            'uri': 'ProductAttributeValue/action/groupsPavs?' + $.param(param)
                        }).success(res => {
                            let otherGroupPavsPerInstances = res;
                            currentGroupPavs.forEach((group) => {
                                tmp[group.key] = {
                                    id: group.id,
                                    key: group.key,
                                    label: group.label,
                                    othersRelationItemsPerModels: [],
                                    currentCollection: group.collection.map(p => {
                                        let pav = model.clone();
                                        pav.set(p)
                                        return pav
                                    })
                                };
                            })

                            otherGroupPavsPerInstances
                                .forEach((otherGroupPavs, index) => {
                                    if ('_error' in otherGroupPavs) {
                                        this.instances[index]['_error'] = otherGroupPavs['_error'];
                                        return;
                                    }
                                    otherGroupPavs.forEach((otherGroup) => {
                                        if (!tmp[otherGroup.key]) {
                                            tmp[otherGroup.key] = {
                                                id: otherGroup.id,
                                                key: otherGroup.key,
                                                label: otherGroup.label,
                                                othersRelationItemsPerModels: [],
                                                currentCollection: []
                                            };
                                        }

                                        tmp[otherGroup.key].othersRelationItemsPerModels[index] = otherGroup.collection
                                            .map(p => {
                                                    for (let key in p) {
                                                        let el = p[key];
                                                        let instanceUrl = this.instances[index].atrocoreUrl;
                                                        if (key.includes('PathsData')) {
                                                            if (el && ('thumbnails' in el)) {
                                                                for (let size in el['thumbnails']) {
                                                                    p[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                                                                }
                                                            }
                                                        }
                                                    }
                                                    let pav = model.clone();
                                                    pav.set(p)
                                                    return pav
                                                }
                                            )
                                    })
                                })
                            this.groupPavsData = Object.values(tmp);
                            this.groupPavsData.map((groupPav, index) => {
                                this.instances.forEach((instance, key) => {
                                    if (!groupPav.othersRelationItemsPerModels[key]) {
                                        this.groupPavsData[index].othersRelationItemsPerModels[key] = [];
                                    }
                                })
                            })
                            this.defaultPavModel = model;

                            this.setupRelationship(() => this.wait(false));
                        })
                    });
                }, this);
            },

            getOthersList() {
                return this.instances;
            },

            updateBaseUrl(view, instanceUrl) {
                Relationship.prototype.updateBaseUrl.call(this, view, instanceUrl);
            }
        })
    })