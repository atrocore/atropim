/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/stream/notes/create-association-product', 'views/stream/notes/relate', function (Dep) {

    return Dep.extend({

        messageName: 'createAssociationProduct',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['mainProduct'] = '<a href="#Product/view/' + data.mainProductId + '">' + data.mainProductName + '</a>';
            this.messageData['relatedProduct'] = '<a href="#Product/view/' + data.relatedProductId + '">' + data.relatedProductName + '</a>';

            this.createMessage();
        }
    });
});

