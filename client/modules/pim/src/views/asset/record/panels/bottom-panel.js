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

Espo.define('pim:views/asset/record/panels/bottom-panel', 'dam:views/asset/record/panels/bottom-panel',
    Dep => Dep.extend({

        actionSetAsMainImage: function (data) {
            const pathData = window.location.hash.replace('#', '').split('/view/');
            const entityName = pathData.shift();
            const entityId = pathData.pop();

            this.notify('Saving...');
            this.ajaxPostRequest(`${entityName}/action/SetAsMainImage`, {
                entityId: entityId,
                assetId: data.asset_id,
                scope: data.scope
            }).then(response => {
                this.notify('Saved', 'success');

                if (response.length) {
                    this.model.set('imagePathsData', response.imagePathsData);
                    this.model.set('imageName', response.imageName);
                    this.model.set('imageId', response.imageId);
                }
            }).done(function () {
                if (this.getParentView() && this.getParentView().getParentView()) {
                    this.getParentView().getParentView().model.fetch();
                }
                this.actionRefresh();
            });
        }

    })
);