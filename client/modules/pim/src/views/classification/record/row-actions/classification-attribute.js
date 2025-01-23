/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification/record/row-actions/classification-attribute', 'views/record/row-actions/relationship-view-and-edit',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);

            list = list.filter(item => item.action !== 'inheritRelated');

            list.push({
                action: 'setCaAsInherited',
                label: this.translate('inherit'),
                data: {
                    id: this.model.id
                }
            });

            if (this.options.acl.delete) {
                list.push({
                    action: 'unlinkRelatedAttribute',
                    label: this.translate('unlinkRelatedAttribute', 'labels', 'ClassificationAttribute'),
                    data: {
                        id: this.model.id
                    }
                });

                list.push({
                    action: 'cascadeUnlinkRelatedAttribute',
                    label: this.translate('cascadeUnlinkRelatedAttribute', 'labels', 'ClassificationAttribute'),
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.once('after:render', () => {
                this.setupIcons()
            });
        },

        setupIcons() {
            let iconContainer = this.$el.find('.list-row-buttons .icons-container');
            if (iconContainer.size() === 0) {
                iconContainer = $("<div class='icons-container'></div>")
                this.$el.find('.list-row-buttons').prepend(iconContainer)
            }

            this.createView('icons', 'pim:views/classification-attribute/fields/icons', {
                el: this.options.el + '.list-row-buttons .icons-container',
                scope: 'ProductAttributeValue',
                model: this.model,
                mode: 'list'
            }, (view) => view.render())
        },
    })
);


