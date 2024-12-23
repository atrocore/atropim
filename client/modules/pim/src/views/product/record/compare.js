/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('pim:views/product/record/compare', 'views/record/compare', function (Dep) {

    return Dep.extend({

        isValidType(type, field) {
            if(field === 'classifications' && this.getConfig().get('allowSingleClassificationForProduct')) {
                return true;
            }
            return  Dep.prototype.isValidType.call(this, type, field);
        },

        isLinkEnabled(model, name) {
            if(name === 'classifications' && this.getConfig().get('allowSingleClassificationForProduct')) {
                return false;
            }
            return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
        },

        isComparableLink(link) {
            if(['productAttributeValues', 'associatedMainProducts'].includes(link)) {
                return true;
            }

            return Dep.prototype.isComparableLink.call(this, link);
        }
    });
});