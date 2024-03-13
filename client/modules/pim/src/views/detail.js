/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/detail', 'views/detail',
    Dep => Dep.extend({

        updateRelationshipPanel(name) {
            let bottom = this.getView('record').getView('bottom');
            if (bottom) {
                let rel = bottom.getView(name);
                if (rel) {
                    if (rel.collection) {
                        rel.collection.fetch();
                    }
                    if (typeof rel.setupList === 'function') {
                        rel.setupList();
                    }
                }
            }
        },

        actionCreateRelatedConfigured: function (data) {
            data = data || {};

            let link = data.link;
            let scope = this.model.defs['links'][link].entity;
            let foreignLink = this.model.defs['links'][link].foreign;
            let fullFormDisabled = data.fullFormDisabled;
            let afterSaveCallback = data.afterSaveCallback;
            let panelView = this.getPanelView(link);

            let attributes = {};

            if (this.relatedAttributeFunctions[link] && typeof this.relatedAttributeFunctions[link] == 'function') {
                attributes = _.extend(this.relatedAttributeFunctions[link].call(this), attributes);
            }

            Object.keys(this.relatedAttributeMap[link] || {}).forEach(function (attr) {
                attributes[this.relatedAttributeMap[link][attr]] = this.model.get(attr);
            }, this);

            this.notify('Loading...');

            let viewName =
                ((panelView || {}).defs || {}).modalEditView ||
                this.getMetadata().get(['clientDefs', scope, 'modalViews', 'edit']) ||
                'views/modals/edit';

            this.createView('quickCreate', viewName, {
                scope: scope,
                relate: {
                    model: this.model,
                    link: foreignLink,
                },
                attributes: attributes,
                fullFormDisabled: fullFormDisabled
            }, function (view) {
                view.model.tabId = data.tabId ?? data.tabId;
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.updateRelationshipPanel(link);
                    this.model.trigger('after:relate', link);

                    if (afterSaveCallback && panelView && typeof panelView[afterSaveCallback] === 'function') {
                        panelView[afterSaveCallback](view.getView('edit').model);
                    }
                }, this);
            }.bind(this));
        },

        actionCreateRelatedEntity(data) {
            let link = data.link;
            let scope = data.scope || this.model.defs['links'][link].entity;
            let afterSaveCallback = data.afterSaveCallback;
            let panelView = this.getPanelView(link);

            let viewName =
                ((panelView || {}).defs || {}).modalEditView ||
                this.getMetadata().get(['clientDefs', scope, 'modalViews', 'edit']) ||
                'views/modals/edit';

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: scope,
                attributes: {},
                fullFormDisabled: true
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    if (afterSaveCallback && panelView && typeof panelView[afterSaveCallback] === 'function') {
                        panelView[afterSaveCallback](view.getView('edit').model);
                    }
                }, this);
            }.bind(this));
        },

        getPanelView(name) {
            let panelView;
            let recordView = this.getView('record');
            if (recordView) {
                let bottomView = recordView.getView('bottom');
                if (bottomView) {
                    panelView = bottomView.getView(name)
                }
            }
            return panelView;
        }
    })
);

