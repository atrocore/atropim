/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/attribute-with-inheritance-sign', 'pim:views/classification-attribute/fields/attribute',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const $a = this.$el.find('a');

            if (this.model.get('isInherited')) {
                $a.attr('style', 'font-style: italic');
            }

            if (this.model.get('isRequired')) {
                $a.html($a.html() + ' *');
            }
        },

    })
);

