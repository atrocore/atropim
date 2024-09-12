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
            list.forEach((item, index) => {
                list[index].data.file = this.model.get('ProductFile__id');
            });

            let prefix = this.getRelationFieldPrefix();
            if (this.isImage() && !this.model.get(prefix + 'isMainImage') && this.options.acl.edit) {
                list.unshift({
                    action: 'setAsMainImage',
                    label: this.translate('setAsMainImage'),
                    data: {
                        id: this.model.get(prefix + 'id')
                    }
                });
            }

            return list;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let prefix = this.getRelationFieldPrefix();
            if (this.$el && this.model.get(prefix + 'isMainImage')) {
                this.$el.parent().addClass('main-image global-main-image');
            }
        },

        getRelationFieldPrefix() {
            let hashParts = window.location.hash.split('/view/');
            let entityType = hashParts[0].replace('#', '');

            return entityType + 'File__';
        },

        isImage() {
            return $.inArray(this.model.get('extension'), this.getMetadata().get('app.file.image.extensions') || []) !== -1;
        },

    })
);