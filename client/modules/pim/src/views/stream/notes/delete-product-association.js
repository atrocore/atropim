/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/stream/notes/delete-product-association', 'views/stream/notes/unrelate', function (Dep) {

    return Dep.extend({

        messageName: 'deleteProductAssociation',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['relatedProduct'] = '<a href="#Product/view/' + data.relatedProductId + '">' + data.relatedProductName + '</a>';
            this.messageData['association'] = '<a href="#Association/view/' + data.associationId + '">' + data.associationName + '</a>';

            this.createMessage();
        }
    });
});

