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

            if (model.get('originalId')) {
                model.set('id', model.get('originalId'));
            }

            this.rowList.push(key);
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
        },

        actionQuickView: function (data) {
            if (data.id) {
                data.id = this.getActualModelId(data);
            }

            Dep.prototype.actionQuickView.call(this, data);
        },

        actionQuickEdit: function (data) {
            if (data.id) {
                data.id = this.getActualModelId(data);
            }

            Dep.prototype.actionQuickEdit.call(this, data);
        },

        getActualModelId(data) {
            let cid = data.id;
            this.collection.forEach((model) => {
                if (model.get('id') === data.id && model.get('ProductFile__id') === data.file && this.collection.get(model.cid)) {
                    cid = model.cid;
                }
            });

            return cid;
        }
    })
);
