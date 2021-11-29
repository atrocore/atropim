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

Espo.define('pim:views/asset/fields/preview', 'dam:views/asset/fields/preview',
    Dep => Dep.extend({
        template: "pim:asset/fields/preview/list",

        data() {
            let result = Dep.prototype.data.call(this);

            let isChannelMainImage = this.model.get('isMainImage');
            let isGlobalMainImage = this.isMainProductImage(this.model.get('fileId'));

            if (isChannelMainImage || isGlobalMainImage) {
                result['isMainImage'] = true;

                if (isGlobalMainImage) {
                    result['globalMainImage'] = true;
                }
            }

            return result;
        },

        isMainProductImage(assetFileId) {
            if (this.getParentView() && this.getParentView().getParentView() && this.getParentView().getParentView().getParentView() && this.getParentView().getParentView().getParentView().getParentView()) {
                let fileId = this.getParentView().getParentView().getParentView().getParentView().model.get('imageId');

                return fileId === assetFileId;
            }

            return false;
        }
    })
);
