/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/list', 'pim:views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let massActionsList = this.getMetadata().get(['clientDefs', this.scope, 'additionalMassActions']) || {};
            Object.keys(massActionsList).forEach((item) => {
                this.massActionList.push(massActionsList[item].name);
                if (massActionsList[item].pushToCheckAllResultMassActionList) {
                    this.checkAllResultMassActionList.push(massActionsList[item].name);
                }
                let method = 'massAction' + Espo.Utils.upperCaseFirst(massActionsList[item].name);
                this[method] = function () {
                    let path = massActionsList[item].actionViewPath;
                    let o = {};
                    (massActionsList[item].optionsToPass || []).forEach((option) => {
                        if (option in this) {
                            o[option] = this[option];
                        }
                    });
                    this.createView(item, path, o, (view) => {
                        if (typeof view[massActionsList[item].action] === 'function') {
                            view[massActionsList[item].action]();
                        }
                    });
                };
            }, this);
        },
    })
);