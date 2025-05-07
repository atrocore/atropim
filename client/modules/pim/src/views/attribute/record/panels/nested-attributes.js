/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/record/panels/nested-attributes', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            notParentCompositeAttribute: function () {
                return this.model.id;
            },

            notLinkedWithCurrent: function () {
                return this.model.id;
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.model.get('type') === 'composite') {
                this.$el.parent().show();
            }
        }

    })
);