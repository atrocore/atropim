/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/fields/attribute-with-required-sign', 'pim:views/product-attribute-value/fields/attribute',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const $a = this.$el.find('a');

            if (this.model.get('isPavRelationInherited')) {
                $a.addClass('inherited-relation');
            }

            if (this.model.get('isRequired')) {
                $a.html($a.html() + ' *');
            }
        },

    })
);

