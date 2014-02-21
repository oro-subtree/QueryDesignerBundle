/*global define*/
define(['underscore', 'backbone', 'oroentity/js/entity-field-view'
    ], function (_, Backbone, EntityFieldView) {
    'use strict';

    /**
     * @export  oroquerydesigner/js/query-designer/column-selector-view
     * @class   oro.queryDesigner.ColumnSelectorView
     * @extends oro.EntityFieldView
     */
    return EntityFieldView.extend({
        /** @property {Object} */
        options: {
            columnChainTemplate: null
        },

        getLabel: function (value) {
            return this.options.columnChainTemplate(this.splitFieldId(value));
        }
    });
});
