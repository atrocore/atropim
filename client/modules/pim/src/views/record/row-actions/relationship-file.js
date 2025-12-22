/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/row-actions/relationship-file', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);
            let model = this.model.relationModel
            list.forEach((item, index) => {
                if (model && list[index].data) {
                    list[index].data.file = model.get('id');
                }
            });

            if (model && this.isImage() && !model.get('isMainImage') && this.options.acl.edit) {
                list.unshift({
                    action: 'setAsMainImage',
                    label: this.translate('setAsMainImage'),
                    data: {
                        id: model.get('id')
                    }
                });
            }

            return list;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let model = this.model.relationModel
            if (model && this.$el && model.get('isMainImage')) {
                this.$el.parent().addClass('main-image global-main-image');
            }
        },


        isImage() {
            return $.inArray(this.model.get('extension'), this.getMetadata().get('app.file.image.extensions') || []) !== -1;
        },

    })
);