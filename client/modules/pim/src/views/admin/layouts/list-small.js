/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/admin/layouts/list-small', 'class-replace!pim:views/admin/layouts/list-small', function (Dep) {

    return Dep.extend({

        layoutDisabledParameter: 'layoutListSmallDisabled',

        setup() {
            Dep.prototype.setup.call(this);

            if(this.scope === 'ProductAttributeValue'){
                this.dataAttributeList.push('editable')

                this.dataAttributesDefs['editable'] = {type: 'bool'}
            }
        }

    });

});