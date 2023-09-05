/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/modals/remote-image-preview', 'views/modals/image-preview',
    Dep => Dep.extend({

        data() {
            return {
                name: this.options.url,
                url: this.options.url,
                originalUrl: this.options.url
            };
        },

    })
);

