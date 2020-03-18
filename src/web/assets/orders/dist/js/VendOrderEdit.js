/** global: Craft */
/** global: Garnish */
/** global: $ */
/**
 * Order Edit Class
 */
if (typeof Craft.Vend === typeof undefined) {
    Craft.Vend = {};
}

Craft.Vend.OrderEdit = Garnish.Base.extend({

    $btn: null,
    $spinner: null,
    sending: false,

    init: function(settings) {
        this.setSettings(settings, this.defaults);

        this.$btn = $('<div class="btn" data-icon-after="upload">Send to Vend</div>');
        this.$spinner = $('<div class="spinner" style="display: none;"></div>');

        var $btnGroup = $('<div></div>')
            .append(this.$btn)
            .append(this.$spinner);

        $('#order-secondary-actions .order-flex')
            .prepend('<div class="spacer"></div>')
            .prepend($btnGroup);

        // Bind the button click
        this.addListener(this.$btn, 'click', 'send');
    },

    send: function(event) {
        event.preventDefault();
        if (this.sending) {
            return;
        }

        // Before we go any further, check if we have a Vend Order ID or not
        if (this.settings.vendOrderId) {
            // TODO: If we do, crack on and send the UPDATE
            return;
        } else {
            // If we don’t then confirm they want to send the order - it might
            // already be in Vend you see
            if (confirm('Are you sure you want to send this order to Vend? Make sure you know it’s not already there. If it is, fetch its ID from Vend and update the Vend Order ID field here first then try again.')) {
                this.registerSale();
            }
        }

    },

    registerSale: function() {
        this.sending = true;
        this.$btn.addClass('active');
        this.$spinner.show();
        Craft.postActionRequest('vend/orders/send', {id: this.settings.commerceOrderId}, $.proxy(function(response, textStatus) {
            this.$btn.removeClass('active');
            this.$spinner.hide();
            this.loading = false;

            if (textStatus === 'success') {
                if (response.success) {
                    Craft.cp.displayNotice('Order sent.');
                } else if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayError('Couldn’t send order.');
                }
            }
        }, this));
    },

    defaults: {
        commerceOrderId: null,
        vendOrderId: null
    }
});
