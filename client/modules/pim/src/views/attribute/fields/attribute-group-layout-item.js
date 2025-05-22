/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/fields/attribute-group-layout-item', 'views/fields/base',
    Dep => Dep.extend({

        readOnly: true,

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().find('label').css('font-weight', 'bold');
        },

    })
);
