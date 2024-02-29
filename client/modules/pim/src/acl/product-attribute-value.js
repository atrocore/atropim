/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('pim:acl/product-attribute-value', 'acl', function (Dep) {

    return Dep.extend({

        checkModelEdit: function (model, data, precise) {
            if (model.get('aclEdit') != null) {
                return model.get('aclEdit')
            }
            return Dep.prototype.checkModel.call(this, model, data, 'edit', precise)
        },

        checkModelDelete: function (model, data, precise) {
            if (model.get('aclDelete') != null) {
                return model.get('aclDelete')
            }
            return Dep.prototype.checkModelDelete.call(this, model, data, precise)
        }
    });
});
