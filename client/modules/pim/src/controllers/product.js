

/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

Espo.define('pim:controllers/product', 'controllers/record', Dep => Dep.extend({

    defaultAction: 'list',

    beforePlate() {
        this.handleCheckAccess('read');
    },

    plate() {
        this.getCollection(function (collection) {
            this.main(this.getViewName('plate'), {
                scope: this.name,
                collection: collection
            });
        });
    },

    list(options) {
        var callback = options.callback;
        var isReturn = options.isReturn;
        if (this.getRouter().backProcessed) {
            isReturn = true;
        }

        var key = this.name + 'List';

        if (!isReturn) {
            var stored = this.getStoredMainView(key);
            if (stored) {
                this.clearStoredMainView(key);
            }
        }

        this.getCollection(function (collection) {
            this.listenToOnce(this.baseController, 'action', function () {
                collection.abortLastFetch();
            }, this);

            this.main(this.getViewName('list'), {
                scope: this.name,
                collection: collection,
                params: options
            }, callback, isReturn, key);
        }, this, false);
    }

}));
