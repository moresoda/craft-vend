/** global: Craft */
/** global: Garnish */
/** global: $ */
/**
 * Full Feed Widget Class
 */
if (typeof Craft.Vend === typeof undefined) {
    Craft.Vend = {};
}

Craft.Vend.FullFeedUtil = Garnish.Base.extend({

    $pane: null,
    $form: null,
    $btn: null,
    working: false,

    init: function(settings) {
        this.setSettings(settings, this.defaults);

        this.$pane = $('#vend-syncutils-full');
        this.$form = this.$pane.find('form:first');
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

        if (this.settings.preRunAction !== "") {
            Craft.postActionRequest(this.settings.preRunAction, {}, $.proxy(function(response, textStatus) {
                this.working = false;
                this.$pane.removeClass('loading');
                this.$btn.removeClass('disabled');

                if (textStatus === 'success') {
                    if (response.success) {
                        this.run();
                    } else if (response.error) {
                        Craft.cp.displayError(response.error);
                    } else {
                        Craft.cp.displayError('Couldn’t trigger pre-sync operation.');
                    }
                } else {
                    Craft.cp.displayError('Couldn’t trigger pre-sync operation.');
                }
            }, this));
        } else {
            this.run();
        }
    },

    run: function() {
        Craft.postActionRequest('vend/feeds/run', {}, $.proxy(function(response, textStatus) {
            this.working = false;
            this.$pane.removeClass('loading');
            this.$btn.removeClass('disabled');

            if (textStatus === 'success') {
                if (response.success) {
                    Craft.cp.runQueue();
                    Craft.cp.displayNotice('Full sync started.');
                } else if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayError('Couldn’t start sync operation.');
                }
            }
        }, this));
    },

    defaults: {
        preRunAction: ""
    }
});