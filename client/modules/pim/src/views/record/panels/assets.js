/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/assets', 'treo-core:views/record/panels/for-relationship-type',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.actionList.unshift({
                label: this.translate('upload', 'labels', 'Asset'),
                action: 'massAssetCreate',
                data: {
                    link: this.link
                },
                acl: 'create',
                aclScope: 'Asset'
            });
        },

        actionMassAssetCreate(data) {
            this.notify('Loading...');
            this.createView('massCreate', 'dam:views/asset/modals/edit', {
                name: 'massCreate',
                scope: 'Asset',
                attributes: {massCreate: true},
                fullFormDisabled: true,
                layoutName: 'detailSmall'
            }, view => {
                view.render();
                view.notify(false);

                this.listenTo(view, 'before:save', attrs => {
                    attrs['_createAssetRelation'] = {
                        entityType: this.model.urlRoot,
                        entityId: this.model.get('id')
                    };
                });

                this.listenToOnce(view, 'after:save', () => {
                    this.actionRefresh();
                    this.model.trigger('after:relate', this.link, this.defs);
                });
            });
        },

        actionSetAsMainImage(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}Asset/${data.id}`, {isMainImage: true}).done(entity => {
                this.model.trigger('asset:saved');
                this.notify('Saved', 'success');
                this.actionRefresh();
            });
        },

    })
);

