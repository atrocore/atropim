/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/settings/edit', 'views/settings/edit', function (Dep) {

    return Dep.extend({

        scope: 'Settings',

        recordView: 'pim:views/admin/settings',

        getHeader() {
            return this.buildHeaderHtml([
                this.getLanguage().translate('pim', 'labels', 'Admin')
            ], true);
        }
    });

});

