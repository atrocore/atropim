/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/channel', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['notLinkedWithClassificationAttribute'],

        boolFilterData: {
            notLinkedWithClassificationAttribute() {
                return {classificationId: this.model.get('classificationId'), attributeId: this.model.get('attributeId')};
            }
        },
        select: function (model) {
            Dep.prototype.select.call(this, model);
            this.model.trigger('change:channel', model);
        },
    })
);

