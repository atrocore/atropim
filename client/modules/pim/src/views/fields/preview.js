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

Espo.define('pim:views/fields/preview', 'view',
    Dep => Dep.extend({

        template: "pim:fields/preview/list",

        events: {
            'click a[data-action="showImagePreview"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                let id = $(e.currentTarget).data('id');
                this.createView('preview', 'dam:views/modals/image-preview', {
                    id: id,
                    model: this.model,
                    type: "asset"
                }, function (view) {
                    view.render();
                });
            }
        },

        data() {
            return {
                "originPath": (this.model.get('filePathsData')) ? this.model.get('filePathsData').download : null,
                "thumbnailPath": (this.model.get('filePathsData')) ? this.model.get('filePathsData').thumbs.small : null,
                "timestamp": this.getTimestamp(),
                "fileId": this.model.get('fileId'),
                "icon": this.model.get('icon')
            };
        },

        getTimestamp() {
            return (Math.random() * 10000000000).toFixed();
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.$el && this.model.get('isMainImage')) {
                this.$el.parent().addClass('main-image global-main-image');
            }
        }
    })
);