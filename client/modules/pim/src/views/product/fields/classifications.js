/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/classifications', 'views/fields/link',
    Dep => Dep.extend({
        setup() {
            let classificationId = this.model.get('classificationsIds')?.at(-1)
            this.model.set('classificationsId',  classificationId ?? null);
            this.model.set('classificationsName', (this.model.get('classificationsNames') ?? [])[classificationId] ?? null);
            Dep.prototype.setup.call(this);
             this.listenTo(this.model, 'change:classificationsId', () => {
                 let name = {}
                 if(this.model.get('classificationsId')){
                     name[this.model.get('classificationsId')] = this.model.get('classificationsName')
                 }
                 this.model.set('classificationsIds',  this.model.get('classificationsId') ? [this.model.get('classificationsId')] :[]);
                 this.model.set('classificationsNames',  name);
             });
        },
    })
);