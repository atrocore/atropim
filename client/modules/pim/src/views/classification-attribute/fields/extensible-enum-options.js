/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('pim:views/classification-attribute/fields/extensible-enum-options', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({
        getSelectFilters(){
            return {
                "where": {
                    "field":"extensibleEnumId",
                    "type":"in",
                    "value": [this.model.get('attributeExtensibleEnumId')]
                }
            }
        }
    });
});


