/** global: Craft */
/** global: Garnish */
/** global: $ */

if (typeof Craft.Vend === typeof undefined) {
    Craft.Vend = {};
}

Craft.Vend.ProductEdit = Garnish.Base.extend({
    init: function(settings) {
        this.setSettings(settings, this.defaults);

        if (this.settings.domainPrefix && this.settings.vendProductId) {
            var $btn = $(`<a href="https://${this.settings.domainPrefix}.vendhq.com/product/${this.settings.vendProductId}" class="btn" data-icon="external" target="_blank">View in Vend</a>`);

            var $btnGroup = $('<div class="btngroup"></div>')
                .append($btn);

            $('#save-btn-container')
                .parent()
                .prepend($btnGroup);
        }
    },

    defaults: {
        domainPrefix: null,
        vendProductId: null
    }
});
