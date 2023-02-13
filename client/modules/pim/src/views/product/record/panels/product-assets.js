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

Espo.define('pim:views/product/record/panels/product-assets', 'views/record/panels/for-relationship-type',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.actionList.unshift({
                label: this.translate('massUpload', 'labels', 'Asset'),
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
                attributes: {},
                fullFormDisabled: true,
                layoutName: 'massCreateDetailSmall'
            }, view => {
                view.render();
                view.notify(false);

                this.listenTo(view, 'before:save', attrs => {
                    attrs['_createProductAssetForProductId'] = this.model.get('id');
                });

                this.listenToOnce(view, 'after:save', () => {
                    this.actionRefresh();
                    this.model.trigger('after:relate', this.link, this.defs);
                });
            });
        },

        actionSetAsMainImage(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`ProductAsset/${data.id}`, {isMainImage: true}).done(entity => {
                this.model.trigger('asset:saved');
                this.notify('Saved', 'success');
                this.actionRefresh();
            });
        },

    })
);

