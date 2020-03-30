/** global: Craft */
/** global: Garnish */
/** global: $ */
/**
 * Full Feed Widget Class
 */
if (typeof Craft.Vend === typeof undefined) {
    Craft.Vend = {};
}

Craft.Vend.FastFeedUtil = Garnish.Base.extend({

    $pane: null,
    $form: null,
    $orderInput: null,
    $limitInput: null,
    $btn: null,
    working: false,

    init: function(settings) {
        this.setSettings(settings, this.defaults);

        this.$pane = $('#vend-syncutils-fast');
        this.$form = this.$pane.find('form:first');
        this.$orderInput = this.$form.find('select[name="order"]:first');
        this.$limitInput = this.$form.find('input[name="limit"]:first');
        this.$btn = this.$form.find('.btn:first');
        this.initForm();
    },

    initForm: function() {
        this.addListener(this.$form, 'submit', function($e) {
            $e.preventDefault();
            this.startFullSync();
        });
    },

    startFullSync: function() {
        if (this.working) {
            return;
        }

        this.working = true;
        this.$pane.addClass('loading');
        this.$btn.addClass('disabled');

        var limit = this.$limitInput.val();
        if (limit === '') {
            limit = 50;
        }

        var order = this.$orderInput.val();
        if (order === '') {
            order = 'vendDateUpdated';
        }

        Craft.postActionRequest('vend/feeds/run', {
            'fastSyncLimit': limit,
            'fastSyncOrder': order
        }, $.proxy(function(response, textStatus) {
            this.working = false;
            this.$pane.removeClass('loading');
            this.$btn.removeClass('disabled');

            if (textStatus === 'success') {
                if (response.success) {
                    Craft.cp.runQueue();
                    Craft.cp.displayNotice('Fast sync started.');
                } else if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayError('Couldn’t start sync operation.');
                }
            }
        }, this));
    },

    defaults: {}
});