/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/stream/notes/create-product-association', 'views/stream/notes/relate', function (Dep) {

    return Dep.extend({

        messageName: 'createProductAssociation',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['relatedProduct'] = '<a href="#' + this.entityType + '/view/Product/' + data.relatedProductId + '">' + data.relatedProductName + '</a>';
            this.messageData['association'] = '<a href="#' + this.entityType + '/view/Association/' + data.associationId + '">' + data.associationName + '</a>';

            this.createMessage();
        }
    });
});

