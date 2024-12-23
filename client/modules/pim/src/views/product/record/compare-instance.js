/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('pim:views/product/record/compare-instance', ['views/record/compare-instance', 'pim:views/product/record/compare'], function (Dep, Compare) {

    return Dep.extend({

        isValidType(type, field) {
            return  Compare.prototype.isValidType.call(this, type, field);
        },

        isLinkEnabled(model, name) {
            return  Compare.prototype.isLinkEnabled.call(this, model, name);
        },

        isComparableLink(link) {
            return Compare.prototype.isComparableLink.call(this, link);
        }
    });
});