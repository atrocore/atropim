/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/fields/full-width', 'views/fields/bool', function (Dep) {

    return Dep.extend({
        setup(){
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                if (['wysiwyg', 'markdown', 'text', 'composite'].includes(this.model.get('type'))) {
                    this.model.set('fullWidth', true);
                }
            });
        },
    })
})
