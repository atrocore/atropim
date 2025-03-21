/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/file/record/list', 'views/file/record/list',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', () => {
                this.collection.reset();
                this.getParentView().reRender();
            });
        },

        buildRow: function (i, model, callback) {
            var key = model.id;

            this.rowList.push(key);

            let getRelationModel = (callback) => {
                if (model.get('__relationEntity')) {
                    this.getModelFactory().create(this.relationScope, relModel => {
                        relModel.set(model.get('__relationEntity'));
                        model.relationModel = relModel
                        callback(relModel)
                    })
                } else {
                    callback()
                }
            }

            getRelationModel((relModel) => {
                this.getInternalLayout(function (internalLayout) {
                    internalLayout = Espo.Utils.cloneDeep(internalLayout);
                    this.prepareInternalLayout(internalLayout, model);

                    const entityDisabled = this.getMetadata().get(['scopes', model.name, 'disabled'])
                    var acl = {
                        edit: entityDisabled ? false : this.getAcl().checkModel(model, 'edit'),
                        delete: entityDisabled ? false : this.getAcl().checkModel(model, 'delete'),
                        unlink: this.options.canUnlink
                    };

                    this.createView(key, 'views/base', {
                        model: model,
                        acl: acl,
                        el: this.options.el + ' .list-row[data-id="' + key + '"]',
                        optionsToPass: ['acl', 'scope'],
                        scope: this.scope,
                        noCache: true,
                        _layout: {
                            type: this._internalLayoutType,
                            layout: internalLayout
                        },
                        name: this.type + '-' + model.name,
                        setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered()
                    }, callback);
                }.bind(this), model);
            })
        }
    })
);
